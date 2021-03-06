<?php

namespace App\Controller;

use App\Entity\Classes;
use App\Entity\Articles;
use App\Repository\ArticlesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;




class ArticlesController extends AbstractController
{
  /**
  * @Security("is_granted('ROLE_SECRETAIRE') or is_granted('ROLE_PROFESSEURRESPONSABLE')")
  * @Route("/article/nouveau", name="article_nouveau")
  * @Route("/article/modifier/{id}", name="article_modifier")
  */
  public function formulaireArticle(Articles $article = null,Request $request, EntityManagerInterface $em)
  {
    $classe = null; //Classe du prof responsable
    $nbClasses = null; //nb de classes de l'article en edit mode
    if($this->getUser()->getRoles()[0] == "ROLE_PROFESSEURRESPONSABLE") $classe = $this->getUser()->getClasseResponsable(); //Savoir si un prof responsable est connecté

    if(!$article)
    {
      $article = new Articles();

      if($classe == null)
      {
        $form = $this->createFormBuilder($article)
        ->add('titre')
        ->add('description', CKEditorType::class, [
          'config' => [
            'uiColor' => '#e2e2e2',
            'toolbar' => 'full',
            'required' => 'true'
          ]
        ])
        ->add('classes', EntityType::class,
        [
          'class' => Classes::class,
          'choice_label' => 'nomClasse',
          'multiple' => 'true',
          'expanded' => 'true',
          'required' => 'true',
          'mapped' => 'true'
        ])
        ->add('important', CheckboxType::class, [
          'required' => false
        ])
        ->getForm();
        $form->handleRequest($request);
      }
      else
      {
        $form = $this->createFormBuilder($article)
        ->add('titre')
        ->add('description', CKEditorType::class, [
          'config' => [
            'uiColor' => '#e2e2e2',
            'toolbar' => 'full',
            'required' => 'true'
          ]
        ])
        ->add('important', CheckboxType::class, [
          'required' => false
        ])
        ->getForm();
        $form->handleRequest($request);
        $article->addClass($this->getUser()->getClasseResponsable());
      }

      $article->setDate(new \DateTime);
    }
    else
    { // Mode edit
      $nbClasses = $article->getClasses()->count();
      if($classe == null)
      {
        $form = $this->createFormBuilder($article)
        ->add('titre')
        ->add('description', CKEditorType::class, [
          'config' => [
            'uiColor' => '#e2e2e2',
            'toolbar' => 'full',
            'required' => 'true'
          ]
        ])
        ->add('classes', EntityType::class,
        [
          'class' => Classes::class,
          'choice_label' => 'nomClasse',
          'label' => 'Classes de l\'article',
          'expanded' => true,
          'multiple' => true,
          'mapped' => true, //décoché par défaut
          'by_reference' => false,
        ])
        ->add('important', CheckboxType::class, [
          'required' => false
        ])
        ->getForm();
      }
      else
      {
        $form = $this->createFormBuilder($article)
        ->add('titre')
        ->add('description', CKEditorType::class, [
          'config' => [
            'uiColor' => '#e2e2e2',
            'toolbar' => 'full',
            'required' => 'true'
          ]
        ])
        ->add('important', CheckboxType::class, [
          'required' => false
        ])
        ->getForm();
      }

      $form->handleRequest($request);
    }

    // Réception du form valide -> add/update
    if($form->isSubmitted() && $form->isValid())
    {
      $em->persist($article);
      $em->flush();
      $this->addFlash('success_modifie','L\'article a bien été ajouté / mis à jour');
      return $this->redirectToRoute('article_rechercher');
    }


    return $this->render('articles/index.html.twig', [
      'form_article' => $form->createView(),
      'editMode' => $article->getId() !== null,
      'articles' => $article,
      'classe' => $classe,
      'nbClasses' => $nbClasses
    ]);
  }

  /**
  * @Security("is_granted('ROLE_SECRETAIRE') or is_granted('ROLE_PROFESSEURRESPONSABLE')")
  * @Route("/article/rechercher", name="article_rechercher")
  */
  public function rechercherArticle(ArticlesRepository $repoA)
  {
    $classe = null;
    $articles = $repoA->findAll();

    if($this->getUser()->getRoles()[0] == "ROLE_PROFESSEURRESPONSABLE") 
    {
      $articles = $repoA->getArticleByClasse($this->getUser()->getClasseResponsable());
      $classe = $this->getUser()->getClasseResponsable();
    }
    
    return $this->render('articles/research.html.twig', [
      'articles' => $articles,
      'classe' => $classe
    ]);

  }

  /**
  * @Security("is_granted('ROLE_SECRETAIRE') or is_granted('ROLE_PROFESSEURRESPONSABLE')")
  * @Route("/article/supprimer/{id}", name="article_supprimer")
  */
  public function supprimerArticle(Articles $article, Request $request, EntityManagerInterface $em)
  {

    //Si le formulaire à été soumis
    if($request->isMethod('POST'))
    {
      // En cas de validation on supprime et on redirige
      if($request->request->has('oui'))
      {
        $em->remove($article);
        $em->flush();
        $this->addFlash('delete',"Cet article a été supprimé avec succès");
      }

      return $this->redirectToRoute('article_rechercher');

    }
    else
    {
      //Si le formulaire n'a pas été soumis alors on l'affiche
      $title = 'Êtes-vous sûr(e) de vouloir supprimer cet article ?';

      $message = 'Article n°'.$article->getId().' ayant pour titre : '.
      $article->getTitre().' datant du '.
      $article->getDate()->format('Y-m-d');

      return $this->render('confirmation.html.twig', [
        'titre' => $title,
        'message' => $message
      ]);
    }
  }

  /**
  * @Route("/article/afficher/{id}", name="article_afficher")
  */
  public function afficherArticle(Articles $article)
  {
      return $this->render('articles/show.html.twig', [
      'article' => $article
    ]);
  }
}
