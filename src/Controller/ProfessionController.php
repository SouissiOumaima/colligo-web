<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfessionController extends AbstractController
{

    #[Route('/profession/{name}', name: 'profession_show')]
    public function showProfession(string $name): Response
    {

        $videoPath = 'videos/' . $name . '.mp4';
        $absolutePath = $this->getParameter('kernel.project_dir') . '/public/' . $videoPath;

        if (!file_exists($absolutePath)) {
            throw $this->createNotFoundException("Fichier vidéo manquant: " . $videoPath);
        }

        $professions = [
            'medecin' => [
                'title' => 'Médecin',
                'description' => 'Les médecins aident les gens à rester en bonne santé et soignent les maladies.',
                'detailed_description' => "Le médecin est un professionnel de santé qui examine les patients, établit des diagnostics et prescrit des traitements. Il peut travailler à l'hôpital ou en cabinet. Les médecins portent souvent une blouse blanche et utilisent des outils comme le stéthoscope.",
                'videos' => ['medecin.mp4'],



            ],

            'enseignant' => [  // <-- Notez la minuscule
                'title' => 'Enseignant',
                'description' => "L'enseignant éduque les enfants...",
                'video' => 'enseignant.mp4',


            ],
            'dentist' => [
                'title' => 'Dentiste',
                'description' => 'Les dentistes prennent soin de nos dents et nous apprennent à bien les brosser.',
                'detailed_description' => "Le dentiste est spécialisé dans la santé bucco-dentaire. Il soigne les caries, fait des détartrages et peut poser des appareils dentaires. Une visite chez le dentiste est recommandée tous les 6 mois pour garder des dents saines.",
                'videos' => ['dentist.mp4'],

            ],
            // Ajoutez d'autres professions...
        ];

        $name = strtolower($name); // Convertit en minuscule

        if (!array_key_exists($name, $professions)) {
            throw $this->createNotFoundException('Profession non trouvée');
        }

        return $this->render('profession/show.html.twig', [
            'profession' => $professions[$name],
        ]);
    }

}