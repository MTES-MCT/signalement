<?php

namespace App\Controller;

use App\Entity\Critere;
use App\Entity\Criticite;
use App\Entity\Signalement;
use App\Entity\Situation;
use App\Repository\SituationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/signalement')]
class FrontSignalementController extends AbstractController
{
    #[Route('/', name: 'signalement')]
    public function index(SituationRepository $situationRepository): Response
    {
        $title = "Signalez vos problèmes de logement";
        $etats = ["Etat moyen", "Mauvais état", "Très mauvais état"];
        $etats_classes = ["moyen", "grave", "tres-grave"];
        return $this->render('front/signalement.html.twig', [
            'title' => $title,
            'situations' => $situationRepository->findAllActive(),
            'etats' => $etats,
            'etats_classes' => $etats_classes
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/envoi', name: 'envoi_signalement', methods: "POST")]
    public function envoi(Request $request, ManagerRegistry $doctrine, SluggerInterface $slugger): Response
    {
        if ($data = $request->get('signalement')) {
            $em = $doctrine->getManager();
            $signalement = new Signalement();
            $files_array = [];
            if ($files = $request->files->get('signalement')) {
                foreach ($files as $key => $file) {
                    foreach ($file as $file_) {
                        $originalFilename = pathinfo($file_->getClientOriginalName(), PATHINFO_FILENAME);
                        // this is needed to safely include the file name as part of the URL
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file_->guessExtension();
                        try {
                            //TODO: Resize coté client
                            $file_->move(
                                $this->getParameter('uploads_dir'),
                                $newFilename
                            );
                        } catch (FileException $e) {
                            // ... handle exception if something happens during file upload
                        }
                        $files_array[$key][] = $newFilename;
                    }
                }
                if (isset($files_array['documents']))
                    $signalement->setDocuments($files_array['documents']);
                if (isset($files_array['photos']))
                    $signalement->setPhotos($files_array['photos']);
            }
            foreach ($data as $key => $value) {
                $method = 'set' . ucfirst($key);
                switch ($key) {
                    case 'situation':
                        foreach ($data[$key] as $idSituation => $criteres) {
                            $situation = $em->getRepository(Situation::class)->find($idSituation);
                            $signalement->addSituation($situation);
                            $data[$key][$idSituation]['label'] = $situation->getLabel();
                            foreach ($criteres as $critere) {
                                foreach ($critere as $idCritere => $criticites) {
                                    $critere = $em->getRepository(Critere::class)->find($idCritere);
                                    $signalement->addCritere($critere);
                                    $data[$key][$idSituation]['critere'][$idCritere]['label'] = $critere->getLabel();
                                    $criticite = $em->getRepository(Criticite::class)->find($data[$key][$idSituation]['critere'][$idCritere]['criticite']);
                                    $signalement->addCriticite($criticite);
                                    $data[$key][$idSituation]['critere'][$idCritere]['criticite']= [$criticite->getId() => ['label' => $criticite->getLabel(),'score'=>$criticite->getScore()]];
                                }
                            }
                        }
                        $signalement->setJsonContent($data[$key]);
                        break;
                    case
                    'dateEntree':
                        $value = new \DateTimeImmutable($value);
                        $signalement->$method($value);
                        break;
                    case
                    'geoloc':
                        $signalement->setGeoloc(["lat" => $data[$key]['lat'], "lng" => $data[$key]['lng']]);
                        break;
                    default:
                        if ($value === "" || $value === " ")
                            $value = null;
                        $signalement->$method($value);
                }
            }
            if(!$signalement->getIsNotOccupant())
            {
                $signalement->setNomDeclarant(null);
                $signalement->setPrenomDeclarant(null);
                $signalement->setMailDeclarant(null);
                $signalement->setStructureDeclarant(null);
                $signalement->setTelDeclarant(null);
            }

            //TODO: Si proprio pas averti mail avec lettre type
            if ($em->getRepository(Signalement::class)->findOneBy([], ['id' => 'DESC'])) {
                $id = $em->getRepository(Signalement::class)->findOneBy([], ['id' => 'DESC'])->getId() + 1;
            } else {
                $id = 1;
            }
            //TODO: Repartir a zéro pour chaque année
            $signalement->setReference((new \DateTime())->format('Y') . '-' . $id);

            $scoresMaxSituation = [];
            $scoreSituation = [];
            foreach ($signalement->getSituations() as $situation) {
                $scoresMaxSituation[$situation->getLabel()] = $scoreSituation[$situation->getLabel()] = 0;
                foreach ($situation->getCriteres() as $critere)
                    foreach ($critere->getCriticites() as $criticite)
                        $scoresMaxSituation[$situation->getLabel()] += $criticite->getScore();
            }
            foreach ($signalement->getCriticites() as $criticite)
                $scoreSituation[$criticite->getCritere()->getSituation()->getLabel()] += $criticite->getScore();
            $score = (array_sum($scoreSituation) / array_sum($scoresMaxSituation))*100;
            if ($signalement->getNbEnfantsM6() || $signalement->getNbEnfantsP6())
                $score = $score * 1.1;
            $signalement->setScoreCreation($score);
            $em->persist($signalement);
            $em->flush();
            return $this->json(['response' => 'success']);
        }
        return $this->json(['response' => 'error'], 400);
    }
}
