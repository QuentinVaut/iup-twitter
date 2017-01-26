<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Message;
use AppBundle\Entity\Status;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $message = new Message($this->getUser());
        $form = $this->createForm('AppBundle\Form\MessageType', $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $message->getPicture();

            $fileName = md5(uniqid()).'.'.$file->guessExtension();

            // Move the file to the directory where brochures are stored
            if($file->guessExtension() == 'mp4')
            {
                $file->move(
                    $this->getParameter('video_directory'),
                    $fileName
                );
            }elseif($file->guessExtension() == 'pdf'){
                $file->move(
                    $this->getParameter('pdf_directory'),
                    $fileName
                );
            }else{
                $file->move(
                    $this->getParameter('pictures_directory'),
                    $fileName
                );
            }

            $message->setPicture($fileName);

            $em = $this->getDoctrine()->getManager();
            $em->persist($message);
            $em->flush();
        }
/*
        $messages = $this->getDoctrine()
            ->getRepository('AppBundle:Message')
            ->findByOrderedByDate();*/

        $em    = $this->get('doctrine.orm.entity_manager');
        $dql   = "SELECT m FROM AppBundle:Message m";
        $query = $em->createQuery($dql);

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1)/*page number*/,
            10/*limit per page*/
        );

        return $this->render('default/index.html.twig', [
            'pagination' => $pagination,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Message $message
     *
     * @Route("/like/{id}", requirements={"id" = "\d+"}, name="like")
     */
    public function likeAction(Message $message)
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new AccessDeniedHttpException();
        }

        // @TODO Vérifier qu'on n'a pas déjà liker le message
        // user getMessagesStatus
        // in array (messageId)

        $status = new Status($message, $user);
        $status->setType('like');

        $em = $this->getDoctrine()->getManager();
        $em->persist($status);
        $em->flush();

        return $this->redirect($this->generateUrl('homepage'));
    }

    /**
     * @param Status $status
     *
     * @Route("/unlike/{id}", requirements={"id" = "\d+"}, name="unlike")
     */
    public function unlikeAction(Status $status)
    {
        $user = $this->getUser();

        // On vérifie qu'on est connecté et qu'on le droit de supprimer ce statut
        if (null === $user || ($status->getUser() !== $user)) {
            throw new AccessDeniedHttpException();
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($status);
        $em->flush();

        return $this->redirect($this->generateUrl('homepage'));
    }

    /**
     * @param Message $message
     *
     * @Route("/retweet/{id}", requirements={"id" = "\d+"}, name="retweet")
     */
    public function retweetAction(Message $message)
    {
        $user = $this->getUser();

        if (null === $user) {
            throw new AccessDeniedHttpException();
        }

        $em = $this->getDoctrine()->getManager();

        // Vérifier qu'on n'essaie pas de retweeter un enfant
        // Si c'est le cas, on RT le parent
        if (null !== $message->getParent()) {
            $retweet = new Message($user, $message->getParent());
            $retweet->setContent($message->getParent()->getContent());
            $retweet->setPicture($message->getParent()->getPicture());

            $em->persist($retweet);
            $em->flush();

            return $this->redirect($this->generateUrl('homepage'));
        }

        // Si on RT un message qu'on a déjà RT par le passé, ça supprime le RT
        $exists = $this
            ->getDoctrine()
            ->getRepository('AppBundle:Message')
            ->findByParentAndUser($user->getId(), $message->getId())
        ;

        if (count($exists) > 0) {
            $em->remove($exists[0]);
            $em->flush();

            return $this->redirect($this->generateUrl('homepage'));
        }

        // On RT
        $retweet = new Message($user, $message);
        $retweet->setContent($message->getContent());
        $retweet->setPicture($message->getPicture());

        $em->persist($retweet);
        $em->flush();

        return $this->redirect($this->generateUrl('homepage'));
    }
}
