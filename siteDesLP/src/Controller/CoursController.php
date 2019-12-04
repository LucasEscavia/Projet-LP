<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Professeurs;
use App\Entity\Etudiants;
use App\Entity\Cours;

class CoursController extends AbstractController
{
    /**
     * @Route("/ent/gestion", name="cours_gest")
     */
    public function gererCours(Request $request)
    {
		/* Récupère le prof connecté */
		$prof = $this->getUser();

		if(! $prof instanceof Professeurs)
			return $this->redirectToRoute('connexion');

       	/* Formulaire d'ajout d'un dossier
       	   de cours */
		$cours = new Cours();

		$form = $this->createFormBuilder($cours)
			->add('nom')
			->add('classes') // classe qui peuvent avoir acces au cours
			->add('coursParent', EntityType::class,
				[
					'class' => Cours::class,
					'choice_label' => 'id',
					'label' => 'Dossier de cours parent',
					'expanded' => false,
					'multiple' => false,
					'required' => false,
				])
			/*
			->add('coursEnfants') - null
			->add('prof') - user
			*/
	 	->getForm();

        $form->handleRequest($request);

		// Réception du form valide -> add/update
		if($form->isSubmitted() && $form->isValid())
		{
			$cours->setProf($prof);

			$manager = $this->getDoctrine()->getManager();
			$manager->persist($cours);
			$manager->flush();
		}

    	/* Récupère ses dossiers de cours */
    	$cours = $prof->getDossiersCours();
		
		/* Va chercher les dossiers racines
		   (sans parent) */
    	// -> faire une requete dans le modele
		$dossiersPrincipaux = [];
    	foreach ($cours as $dossier) {
	    	if($dossier->getCoursParent() == null)
    			$dossiersPrincipaux[] = $dossier;
       	}

    	/* Affichage */
		return $this->render('cours/gestion.html.twig', [
            'dossiersRacines' => $dossiersPrincipaux,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/ent/cours", name="cours_affi")
     */
    public function afficherCours()
    {
    	/* Récuperation de l'étudiant connecté */
		$etu = $this->getUser();

		//$etu->getRoles()
		if(! $etu instanceof Etudiants)
			return $this->redirectToRoute('connexion');

    	/* Va chercher la classe de l'étudiant */
    	$classe = $etu->getClasseEtudiant();

    	/* Va chercher les dossiers de cours
    	   auquels la classe à accès */
    	$cours = $classe->getCours();

    	// Crée l'arborescence des dossiers accessibles
    	//-> simplifier avec requete modele
    	$dossiers = [];
    	foreach ($cours as $dossier) {
			//Vérifie si parent est déjà dans la liste
			$skip = false;
			foreach ($dossiers as $parent) {
				if($dossier->getCoursParent() !== null)
					if($dossier->getCoursParent()->getId()==$parent->getId()){
						$parent->addCoursEnfant($dossier);
						//$dossiers[] = $dossier;
						$skip = true;
					}
			}

			if(!$skip) {
				// Copie des enfants
				$listeEnfants = $dossier->getCoursEnfants();

				//Vidage des enfants
				/*foreach ($dossier->getCoursEnfants() as $enfant) {
					$dossier->removeCoursEnfant($enfant);
				}*/

				foreach ($listeEnfants as $enfant) {
					foreach ($cours as $dir) {
						// Si enfant est dans liste d origine
						if($enfant->getId() == $dir->getId()){
							// Enleve enfant de la liste finale si il a deja ete ajouté
							$id = array_search($enfant, $dossiers);
							if($id !== false) 
								array_splice($dossiers, $id);
							
							// Le déplace dans son parent
							$dossier->addCoursEnfant($enfant);
							//$parentID = array_search($dossier, $dossiers);
							//$dossiers[$parentID]->addCoursEnfant($enfant);

							// Supprime de la liste de cours
							$dir = null;
						}
					}
				}

				//Ajout
				$dossiers[] = $dossier;
			}
		}

    	/* Affichage */
		return $this->render('cours/affichage.html.twig', [
            'data' => $dossiers,
        ]);
    }

    /**
     * @Route("/ent/cours/delete/{id}", name="cours_delete")
     */
    public function supprimeCours(Cours $cours, Request $req)
    {
		/* Récupère le prof connecté */
		$prof = $this->getUser();

		if(! $prof instanceof Professeurs)
			return $this->redirectToRoute('connexion');

		//Si le formulaire à été soumis
		if($req->isMethod('POST'))
		{
    		// En cas de validation on supprime et on redirige
			if($req->request->has('oui'))
			{
				$em=$this->getDoctrine()->getManager();
				$em->remove($cours);
				$em->flush();
      			$this->addFlash('delete',"Ce cours et tout ce qu'il contenais a été supprimé avec succès");
			}
			return $this->redirectToRoute('cours_gest');
		} else {
			//Si le formulaire n'a pas été soumis alors on l'affiche
			$title = 'Êtes-vous sûr(e) de vouloir supprimer ce dossier et tout ce qu\'il contient ?';

			$message = 'Le dossier "'.$cours->getNom().'" sera supprimé de manière irréversible.';

    		return $this->render('confirmation.html.twig', [
					'titre' => $title,
					'message' => $message
	        	]);
	    }
    }
}

