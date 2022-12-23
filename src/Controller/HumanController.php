<?php

namespace App\Controller;

use App\ListClass;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\Human;
use App\Form\HumanFormType;
use App\Repository\HumanRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HumanController extends AbstractController
{
    private $entityManager;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->entityManager = $doctrine->getManager();
    }

    #[Route('/human/index', name: 'app_human_index')]
    public function index(Request $request, ManagerRegistry $managerRegistry): Response
    {
        $page = (int)$request->query->get('page') ? (int)$request->query->get('page') : 1;
        $list = new ListClass('/human/index', $managerRegistry->getConnection("default"),
                              'SELECT id, first_name, last_name, age FROM human', $page);

        $list->addColumn('first_name', 'First Name');
        $list->addColumn('last_name', 'Last Name');
        $list->addColumn('age', 'Age');
        $list->setAction('/human/edit', 'Edit/Delete', ['id']);

        $htmlArr = $list->getHTML();

        return $this->render('human/index.html.twig', ['htmlList' => $htmlArr['htmlList'], 
                                                       'htmlPages' => $htmlArr['htmlPages'],
                                                       'rowsCount' => $htmlArr['rowsCount']]);
    }

    #[Route('/human/new', name: 'app_human_new')]
    public function new(Request $request, ValidatorInterface $validator): Response
    {
        $human = new Human();
        $form = $this->createForm(HumanFormType::class, $human);
        $form->handleRequest($request);
        $errors = $validator->validate($form);

        if ($form->isSubmitted() && $form->isValid())
        {
            $human = $form->getData();
            $this->entityManager->persist($human);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_human_index');
        }

        return $this->render('human/new.html.twig', ['form' => $form, 'errors' => $errors]);  
    }

    #[Route('/human/edit', name: 'app_human_edit')]
    public function edit(Request $request, ValidatorInterface $validator, HumanRepository $humanRepo): Response
    {
        $human = $humanRepo->find($request->get('id'));
        $form = $this->createForm(HumanFormType::class, $human);
        $form->handleRequest($request);
        $errors = $validator->validate($form);

        if ($form->isSubmitted() && $form->isValid())
        {
            $human = $form->getData();
            $this->entityManager->persist($human);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_human_index');
        }

        return $this->render('human/edit.html.twig', ['form' => $form, 'errors' => $errors, 'human' => $human]);  
    }

    #[Route('/human/delete/{id}', name: 'app_human_delete', requirements: ['id' => '\d+'])]
    public function delete(Human $human): Response
    {
        $this->entityManager->remove($human);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_human_index');
    }
}