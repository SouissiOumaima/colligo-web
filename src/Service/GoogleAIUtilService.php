<?php

namespace App\Service;

use App\Repository\JeudedevinetteRepository;
use Psr\Log\LoggerInterface;
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
        LoggerInterface $logger,
        string $googleApiKey
    ) {
        $this->apiKey = $googleApiKey;
        $this->httpClient = $httpClient;
        $this->jeudedevinetteRepository = $jeudedevinetteRepository;
        $this->logger = $logger;
    }

    public function getWordsForLevelAndLanguage(string $level, string $language): string
    {
        try {
            if (empty($this->apiKey)) {
                throw new \RuntimeException('Clé API Google manquante.');
            }

            $prompt = $this->generatePrompt($level, $language);
            if ($prompt === 'Langue non prise en charge.') {
                throw new \InvalidArgumentException($prompt);
            }

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
            ], JSON_THROW_ON_ERROR);

            $this->logger->debug('Envoi de la requête à l\'API Google', [
                'url' => $this->apiUrl,
                'prompt' => $prompt,
                'language' => $language,
                'level' => $level,
            ]);

            $response = $this->httpClient->request('POST', $this->apiUrl . '?key=' . $this->apiKey, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $jsonBody,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $errorContent = $response->getContent(false);
                $this->logger->error('Erreur de requête API', ['status' => $statusCode, 'content' => $errorContent]);
                throw new \RuntimeException('Erreur de requête API : ' . $errorContent);
            }

            $responseBody = $response->toArray(false);
            $this->logger->debug('Réponse de l\'API Google', ['response' => $responseBody]);

            $words = $this->extractWordsFromResponse($responseBody);
            if ($words === 'Aucun mot généré.') {
                throw new \RuntimeException($words);
            }

            $this->saveWordsToDatabase($words, $language, $level);

            return $words;
        } catch (\JsonException $e) {
            $this->logger->error('Erreur JSON dans getWordsForLevelAndLanguage', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Erreur : Problème de formatage JSON.');
        } catch (\Throwable $e) {
            $this->logger->error('Erreur dans getWordsForLevelAndLanguage', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function generatePrompt(string $level, string $language): string
    {
        $wordCount = (int) $level + 3;
        $correctWordCount = $wordCount - 1;
        $difficultyDescription = match ($level) {
            '1' => 'très facile',
            '2' => 'un peu difficile',
            '3' => 'très difficile',
            default => 'standard',
        };

        return match (strtolower($language)) {
            'français' => "Conçois les niveaux pour des enfants âgés de 4 à 12 ans, pas pour des adultes. Donne-moi 11 lots de $wordCount mots (dont $correctWordCount mots corrects et 1 mot hors thème) en français pour le niveau $difficultyDescription. Formate chaque lot strictement comme suit : 'mots_corrects_séparés_par_des_espaces - mot_hors_thème - thème'. Exemple : 'chat chien souris - pomme - animaux'. Utilise des espaces pour séparer les mots corrects, et des tirets (' - ') pour séparer les trois parties. Respecte impérativement ce format, rien d'autre. Aucun texte supplémentaire, explication ou information ne doit être généré.",
            'anglais' => "Design the levels for children aged 4 to 12, not for adults. Give me 11 sets of $wordCount words (including $correctWordCount correct words and 1 outlier word) in English for level $difficultyDescription. Format each set strictly as follows: 'correct_words_separated_by_spaces - outlier_word - theme'. Example: 'cat dog mouse - apple - animals'. Use spaces to separate correct words, and dashes (' - ') to separate the three parts. Follow this format strictly, nothing else. No additional text, explanations, or information should be generated.",
            'allemand' => "Gestalte die Niveaus für Kinder im Alter von 4 bis 12 Jahren, nicht für Erwachsene. Gib mir 11 Gruppen von $wordCount Wörtern (davon $correctWordCount richtige Wörter und 1 fremdes Wort) auf Deutsch für das Niveau $difficultyDescription. Formatiere jede Gruppe streng wie folgt: 'richtige_Wörter_mit_Leerzeichen_getrennt - fremdes_Wort - Thema'. Beispiel: 'Katze Hund Maus - Apfel - Tiere'. Verwende Leerzeichen, um die richtigen Wörter zu trennen, und Bindestriche (' - ') zur Trennung der drei Teile. Halte dich strikt an dieses Format, rien d'autre. Kein zusätzlicher Text, Erklärungen oder Informationen dürfen generiert werden.",
            'espagnol' => "Diseña los niveles para niños de 4 a 12 años, no para adultos. Dame 11 grupos de $wordCount palabras (incluyendo $correctWordCount palabras correctas y 1 palabra extranjera) en español para el nivel $difficultyDescription. Formatea cada grupo estrictamente de la siguiente manera: 'palabras_correctas_separadas_por_espacios - palabra_extranjera - tema'. Ejemplo: 'gato perro ratón - manzana - animales'. Usa espacios para separar las palabras correctas y guiones (' - ') para separar las tres partes. Respeta estrictamente este formato, nada más. No se debe generar ningún texto adicional, explicaciones o información.",
            default => 'Langue non prise en charge.'
        };
    }

    private function extractWordsFromResponse(array $responseBody): string
    {
        if (isset($responseBody['error'])) {
            $this->logger->error('Erreur dans la réponse API', ['error' => $responseBody['error']]);
            throw new \RuntimeException('Erreur API : ' . ($responseBody['error']['message'] ?? 'Erreur inconnue'));
        }

        if (empty($responseBody['candidates']) || !is_array($responseBody['candidates'])) {
            $this->logger->warning('Réponse API invalide : candidates manquant ou invalide', ['response' => $responseBody]);
            throw new \RuntimeException('Aucun mot généré : candidates manquant.');
        }

        if (empty($responseBody['candidates'][0]['content']['parts']) || !is_array($responseBody['candidates'][0]['content']['parts'])) {
            $this->logger->warning('Réponse API invalide : parts manquant ou invalide', ['response' => $responseBody]);
            throw new \RuntimeException('Aucun mot généré : parts manquant.');
        }

        $text = trim($responseBody['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (empty($text)) {
            $this->logger->warning('Aucun texte trouvé dans la réponse API', ['response' => $responseBody]);
            throw new \RuntimeException('Aucun mot généré : texte vide.');
        }

        return $text;
    }

    private function saveWordsToDatabase(string $words, string $language, string $level): void
    {
        try {
            // Remove trailing " -" from each line
            $words = preg_replace('/ - $/', '', $words);
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
                } else {
                    $this->logger->warning('Format de lot invalide', [
                        'lot' => $lot,
                        'actualParts' => count($parts),
                    ]);
                }
            }

            if (empty($lotData)) {
                $this->logger->error('Aucun lot valide à enregistrer', ['words' => $words]);
                throw new \RuntimeException('Aucun lot valide à enregistrer.');
            }

            $this->jeudedevinetteRepository->saveLots($lotData, $language, $level);
            $this->logger->info("Données enregistrées pour $language niveau $level", ['lots' => count($lotData)]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de l\'enregistrement des mots', [
                'exception' => $e->getMessage(),
                'language' => $language,
                'level' => $level,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}