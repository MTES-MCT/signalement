<?php

namespace App\Controller;

use App\Entity\Signalement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/connexion", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $title = 'Connexion';
         if ($this->getUser()) {
             return $this->redirectToRoute('back_index');
         }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['title'=>$title,'last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/oneclickconnect", name="login_check")
     */
    public function check()
    {
        throw new \LogicException('This code should never be reached');
    }


    #[Route('/{folder}/{file}',name:'show_uploaded_file',requirements: ['folder' => '_up|_capture'])]
    public function showUploadedFile($folder,$file,Signalement|null $signalement = null): BinaryFileResponse|RedirectResponse
    {
        $request = Request::createFromGlobals();
        if(!$this->isGranted('ROLE_USER_PARTENAIRE') && !$this->isCsrfTokenValid('suivi_signalement_ext_file_view',$request->get('t')))
            return $this->redirectToRoute('home');
        if($folder === '_up')
            $folder = $this->getParameter('uploads_dir');
        if($folder === '_capture')
            $folder = $this->getParameter('capture_dir');
        return new BinaryFileResponse($folder.$file);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
