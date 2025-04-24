<?php

namespace App\Service;

use App\Repository\JeudedevinetteRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleAIUtilService
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private HttpClientInterface $httpClient;
    private JeudedevinetteRepository $jeudedevinetteRepository;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        JeudedevinetteRepository $jeudedevinetteRepository,
        LoggerInterface $logger
    ) {
        $this->apiKey = 'AIzaSyBg0K7lZ85PHUj-zWWcE1uBqTAhzUprIyc';
        $this->httpClient = $httpClient;
        $this->jeudedevinetteRepository = $jeudedevinetteRepository;
        $this->logger = $logger;
    }

    /**
     * Generate words for a given level and language, and save to database.
     *
     * @param string $level
     * @param string $language
     * @return string
     */
    public function getWordsForLevelAndLanguage(string $level, string $language): string
    {
        if (empty($this->apiKey)) {
            return 'Erreur : Clé API manquante.';
        }

        $prompt = $this->generatePrompt($level, $language);
        $jsonBody = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.9,
                'topP' => 1.0,
            ],
        ]);

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '?key=' . $this->apiKey, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $jsonBody,
            ]);

            if ($response->getStatusCode() !== 200) {
                return 'Erreur de requête : ' . $response->getContent(false);
            }

            $responseBody = $response->toArray();
            $words = $this->extractWordsFromResponse($responseBody);

            // Save the words to the database
            $this->saveWordsToDatabase($words, $language, $level);

            return $words;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Error fetching words from Google AI: ' . $e->getMessage());
            return 'Erreur : Impossible de récupérer des mots.';
        }
    }

    /**
     * Generate the prompt based on level and language.
     *
     * @param string $level
     * @param string $language
     * @return string
     */
    private function generatePrompt(string $level, string $language): string
    {
        $wordCount = (int) $level + 3;
        $difficultyDescription = match ($level) {
            '1' => 'très facile',
            '2' => 'un peu difficile',
            '3' => 'très difficile',
            default => 'standard',
        };

        return match (strtolower($language)) {
            'français' => "Donne-moi 11 lots de $wordCount mots dans un même thème en français pour le niveau $difficultyDescription avec 1 mot hors thème. Formate chaque lot strictement comme ceci : 'les mots corrects - mot hors thème - thème'. Respecte impérativement ce format, rien d'autre. Aucun autre texte ou information ne doit être généré.",
            'anglais' => "Give me 11 sets of $wordCount words in English on the same theme for level $difficultyDescription with 1 outlier word. Format each set strictly as follows: 'the correct words - outlier word - theme'. Follow this format strictly, nothing else. No additional text or information should be generated.",
            'allemand' => "Gib mir 11 Gruppen von $wordCount Wörtern auf Deutsch zu einem Thema für das Niveau $difficultyDescription mit 1 fremdem Wort. Formatiere jede Gruppe streng wie folgt: 'die richtige Wörter - fremdes Wort - Thema'. Halte dich strikt an dieses Format, nichts anderes. Kein weiterer Text oder Informationen dürfen generiert werden.",
            'espagnol' => "Dame 11 grupos de $wordCount palabras en español sobre un tema para el nivel $difficultyDescription con 1 palabra extranjera. Formatea cada grupo estrictamente de la siguiente manera: 'las palabras correctas - palabra extranjera - tema'. Respeta estrictamente este formato, nada más. No se debe generar ningún texto o información adicional.",
            default => 'Langue non prise en charge.',
        };
    }

    /**
     * Extract words from the API response.
     *
     * @param array $responseBody
     * @return string
     */
    private function extractWordsFromResponse(array $responseBody): string
    {
        if (!empty($responseBody['candidates'])) {
            $firstCandidate = $responseBody['candidates'][0];
            if (!empty($firstCandidate['content']['parts'])) {
                return trim($firstCandidate['content']['parts'][0]['text']);
            }
        }
        return 'Aucun mot généré.';
    }

    /**
     * Save words to the database.
     *
     * @param string $words
     * @param string $language
     * @param string $level
     */
    private function saveWordsToDatabase(string $words, string $language, string $level): void
    {
        $lots = array_filter(array_map('trim', explode("\n", $words)));
        $lotData = [];

        foreach ($lots as $lot) {
            $parts = array_map('trim', explode(' - ', $lot));
            if (count($parts) === 3) {
                $lotData[] = [
                    'rightWord' => $parts[0],
                    'wrongWord' => $parts[1],
                    'theme' => $parts[2],
                ];
            }
        }

        if (!empty($lotData)) {
            $this->jeudedevinetteRepository->saveLots($lotData, $language, $level);
            $this->logger->info("Données enregistrées pour $language niveau $level");
        } else {
            $this->logger->error('Erreur : Aucun lot valide à enregistrer.');
        }
    }
}