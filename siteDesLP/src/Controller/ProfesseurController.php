<?php
namespace App\Controller;

/** Symfony **/
use App\Entity\Classes;
use App\Entity\Professeurs;
use App\Repository\ClassesRepository;
use App\Repository\ProfesseursRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Security\Core\User\UserInterface;

/** Doctrine **/
use Symfony\Component\Form\Extension\Core\Type\DateType;

/** Propriétaire **/
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

class ProfesseurController extends AbstractController
{
    /**
     * @Route("/professeur/new", name="prof_add")
     * @Route("/professeur/edit/{id}", name="prof_edit")
     */
    public function form(
    	Professeurs $prof = null,
		ProfesseursRepository $repoP,
		Request $request,
		ObjectManager $manager,
		UserPasswordEncoderInterface $encoder
	)
    {
		if(!$prof/*Mode ajout*/){
			$prof = new Professeurs();

			$form = $this->createFormBuilder($prof)
				->add('nomProfesseur')
			    ->add('prenomProfesseur')
				->add('dateNaissance', DateType::class,
					['widget' => 'single_text'])
          ->add('password', PasswordType::class, [
            'attr' => ['maxlength' => '64']
          ])
			    ->add('classes', EntityType::class, [
			        'class' => Classes::class,
			        'choice_label' => 'nomClasse',
			        'label' => 'Classes du professeur',
			        'expanded' => true,
			        'multiple' => true,
			        'mapped' => false, //décoché par défaut
			        'by_reference' => false,
			        /*'group_by' => function($choice, $key, $val) {
			            return strtoupper(substr($choice->getNomClasse(), 0, 1));
			        },*/
			        ])
			    ->getForm();

	        $form->handleRequest($request);

			// Ajouts des classes d'un prof
			$classesProf = $form['classes']->getData();
			if ($classesProf) {
				for ($i=0; $i < sizeof($classesProf); $i++) {
					$classe=$classesProf[$i];
					$prof->getClasses()->add($classe);
				}
			}

	        // Gestion des champs générés
			$prenom = preg_replace(
				'/[^A-Za-z0-9\-]/', '',strtolower(
					trim($prof->getPrenomProfesseur())
				)
			);
			$nom = preg_replace(
				'/[^A-Za-z0-9\-]/', '',strtolower(
					trim($prof->getNomProfesseur())
				)
			);

			$mailAcademique = $prenom.".".$nom;

			$prenom = substr($prenom, 0,1);
			$login = strtolower($nom).$prenom;

			$i = "";
			$j = "";

			while($repoP->findBy(['login' => $login.$i]))
			{
			if($i == "") $i = 0;
			$i++;
			}

			while($repoP->findBy(['mailAcademique' => $mailAcademique.$j."@umontpellier.fr"]))
			{
			if($j == "") $j = 0;
			$j++;
			}

			$prof->setLogin($login.$i);
			$prof->setMailAcademique($mailAcademique.$j."@umontpellier.fr");

			$prenom = ucfirst(strtolower($form['prenomProfesseur']->getData()));
			$nom = strtoupper($form['nomProfesseur']->getData());

			$prof->setNomProfesseur($nom);
			$prof->setprenomProfesseur($prenom);

			// Encodage du mot de passe


		} else { // Mode edit

			$form = $this->createFormBuilder($prof)
				->add('nomProfesseur')
			    ->add('prenomProfesseur')
			    ->add('login')
      			->add('mailAcademique')
				->add('dateNaissance', DateType::class,
					['widget' => 'single_text'])
				//->add('password', PasswordType::class)
			    ->add('classes', EntityType::class, [
			        'class' => Classes::class,
			        'choice_label' => 'nomClasse',
			        'label' => 'Classes du professeur',
			        'expanded' => true,
			        'multiple' => true,
			        'mapped' => true,
			        //'by_reference' => false,
			    ])
				->add('classeResponsable',
	    			EntityType::class,
	    			[
	    				'class' => Classes::class,
	    				'choice_label' => 'nomClasse',
	    				'required' => false,
		'query_builder' => function (ClassesRepository $repoC) use ( $prof ) {
				return $repoC->createQueryBuilder('c')
					->innerJoin('c.professeurs', 'p')
					->where('c.professeurResponsable is NULL')
					->orWhere('c.professeurResponsable = :id')
					->orderBy('c.nomClasse','ASC')
    				->setParameter('id', $prof->getId());
			},
		'group_by' => function($choice, $key, $val) {
			return strtoupper(substr($choice->getNomClasse(), 0, 1));
		},
	    		])
			    ->getForm();

	        $form->handleRequest($request);
		}

		// Réception du form valide -> add/update
		if($form->isSubmitted() && $form->isValid()){

      $hash = $encoder->encodePassword($prof, $prof->getPassword());
      $prof->setPassword($hash);

			$manager->persist($prof);
			$manager->flush();

			return $this->redirectToRoute('prof_search');
		}


        return $this->render('professeur/index.html.twig', [
			'form' => $form->createView(),
			'editMode' => $prof->getId() !== null,
			'prof' => $prof/*,
			'classes' => $classes*/
        ]);
    }

    /**
     * @Route("/professeur/remove/{id}", name="prof_delete")
     */
    public function delete(Professeurs $prof, Request $req)
    {
    	//Si le formulaire à été soumis
    	if($req->isMethod('POST')){
    		// En cas de validation on supprime et on redirige
			if($req->request->has('oui')) {
				$em=$this->getDoctrine()->getManager();
				$em->remove($prof);
				$em->flush();
			}
			// Sinon on redirige simplement
			return $this->redirectToRoute('prof_search');
		} else {
			//Si le formulaire n'a pas été soumis alors on l'affiche
			$title = 'Êtes-vous sûr(e) de vouloir supprimer ce professeur ?';

			$message = 'N°'.$prof->getId().' : '.
				$prof->getPrenomProfesseur().' '.
				$prof->getNomProfesseur(). ' ('.
				$prof->getLogin().')';

    		return $this->render('confirmation.html.twig', [
					'titre' => $title,
					'message' => $message
	        	]);
	    	}
    }

    /**
     * @Route("/professeur/search", name="prof_search")
     */
    public function search(ProfesseursRepository $repo)
    {
        return $this->render('professeur/research.html.twig', [
            'profs' => $repo->findAll(),
        ]);
	}

	 /**
     * @Route("professeur_account", name="professeur_account")
     */
    public function monCompte(UserInterface $prof)
    {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
			// Sinon on déclenche une exception « Accès interdit »
			throw new AccessDeniedException("L'administrateur n'a pas accès à ceci.");
		  }

        $prof = $this->getUser();
        return $this->render('professeur/moncompte.html.twig', [
            'prof' => $prof,
        ]);
    }

    /**
     * @Route("/professeur/importCsv",name="prof_importCsv")
     *
     */
    public function importCsv(){
        $file=$_POST['importProf'];
        return $this->render("importConfirmation.html.twig",
            ['file'=> $file]);
    }
}
