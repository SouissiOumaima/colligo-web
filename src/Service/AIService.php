<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class AIService
{
    private const API_KEY = 'AIzaSyDCuHEdjaEw0E8RITlTzGbRJjghlgFc9BA';
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . self::API_KEY;
    private const MAX_TOKENS = 2048;
    private const TEMPERATURE = 1.0;
    private const TOP_P = 0.9;
    private const TOP_K = 40;

    private Client $httpClient;
    private Serializer $serializer;

    public function __construct()
    {
        $this->httpClient = new Client([
            'connect_timeout' => 60,
            'read_timeout' => 90,
        ]);

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    /**
     * Generate multiple "Fill in the Blank" questions based on theme, level, and language.
     *
     * @param string $theme The theme of the questions
     * @param int $level The difficulty level (1-3)
     * @param string $language The language of the questions
     * @return array<string> List of generated questions or an error message
     */
    public function generateMultipleFillInTheBlank(string $theme, int $level, string $language): array
    {
        $prompt = $this->buildPrompt($theme, $level, $language);
        $result = $this->callAIAPI($prompt);

        if (str_starts_with($result, 'Error')) {
            return [$result]; // Return array with error message
        }

        // Validate the response format
        $lines = array_filter(array_map('trim', explode("\n", $result)));
        $questionPattern = '/^Question:\s*(.*?)\s*:\s*\[(.*?)\]$/';
        $answerPattern = '/\*([^*]+)\*|([^*,\[\]]+)/';
        $validQuestions = [];

        foreach ($lines as $line) {
            if (preg_match($questionPattern, $line, $match)) {
                $answersString = trim($match[2]);
                $answers = [];
                preg_match_all($answerPattern, $answersString, $answerMatches, PREG_SET_ORDER);

                foreach ($answerMatches as $answerMatch) {
                    $answer = !empty($answerMatch[1]) ? trim($answerMatch[1]) : trim($answerMatch[2]);
                    $answers[] = $answer;
                }

                // Check for empty answers
                $answers = array_filter($answers, fn($answer) => !empty($answer));
                if (count($answers) === 3) {
                    $validQuestions[] = $line;
                }
            }
        }

        if (empty($validQuestions)) {
            return ["Error: No valid questions generated. Response: " . $result];
        }



        return [$result]; // Return array with raw response
    }
    private function callAIAPI(string $prompt): string
    {
        $generationConfig = [
            'maxOutputTokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'topP' => self::TOP_P,
            'topK' => self::TOP_K,
        ];

        $userContent = [
            'parts' => [['text' => $prompt]],
            'role' => 'user',
        ];

        $requestBody = [
            'contents' => [$userContent],
            'generationConfig' => $generationConfig,
        ];

        $jsonBody = $this->serializer->serialize($requestBody, 'json');

        try {
            $response = $this->httpClient->post(self::API_URL, [
                'body' => $jsonBody,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $responseBody = (string) $response->getBody();
                return "Error: API returned status " . $response->getStatusCode() . ". Response: " . $responseBody;
            }

            $responseBody = (string) $response->getBody();
            $parsedResponse = $this->serializer->decode($responseBody, 'json');

            return $this->extractOutput($parsedResponse);
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse()
                ? "Error: " . $e->getResponse()->getStatusCode() . " - " . (string) $e->getResponse()->getBody()
                : "Error: Network error - " . $e->getMessage();
            return $errorMessage;
        }
    }

    private function extractOutput(array $response): string
    {
        // Check for API-level errors
        if (isset($response['error'])) {
            return "Error: API error - " . ($response['error']['message'] ?? 'Unknown error');
        }

        if (empty($response) || !isset($response['candidates']) || empty($response['candidates'])) {
            return "Error: No valid response from API.";
        }

        $firstCandidate = $response['candidates'][0];

        // Check for blocked content due to safety settings
        if (isset($firstCandidate['finishReason']) && $firstCandidate['finishReason'] === 'SAFETY') {
            return "Error: Content generation blocked due to safety concerns.";
        }

        if (empty($firstCandidate['content']) || empty($firstCandidate['content']['parts']) || empty($firstCandidate['content']['parts'])) {
            return "Error: Empty response content.";
        }

        $rawText = $firstCandidate['content']['parts'][0]['text'] ?? '';
        if (empty($rawText)) {
            return "Error: No text content in response.";
        }

        return $rawText;
    }

    private function buildPrompt(string $theme, int $level, string $language): string
    {
        //$examples = $this->getExamplesForLanguage($language);

        return sprintf(
            "You are an expert in generating educational content. Generate exactly 10 distinct and varied 'Fill in the Blanks' questions in %s on the theme '%s'. " .
                "Difficulty level: %d (1 = easy, 2 = medium, 3 = hard). " .
                "Each question MUST be unique and cover different aspects of the theme. Do not repeat similar questions or concepts. " .
                "Each question MUST have exactly one blank (represented as '____') and provide exactly three answer choices: one correct and two incorrect. " .
                "All answer choices MUST be non-empty, valid words or phrases relevant to the theme and language. Do not include empty answers (e.g., ', ,'). " .
                "Mark the correct answer by placing asterisks around it (e.g., *correct answer*). " .
                "Randomly shuffle the positions of the correct and incorrect answers in the list. " .
                "Output each question in the following format: 'Question: [question text] : [answer1, *correct answer*, answer3]' " .
                "Each question must be on a new line. Do not include any additional text, headings, or explanations beyond the questions themselves. " .
                // "Here are a few examples to show the exact format:\n%s\n" .
                "Now generate the 5 questions following the rules above.",
            $language,
            $theme,
            $level,
            // $examples
        );
    }

    // private function getExamplesForLanguage(string $language): string
    // {
    //     return match (strtolower($language)) {
    //         'german' => "Question: Der Himmel ist ____ : [rot, *blau*, grün]\n" .
    //             "Question: Ein Apfel kann ____ sein : [*rot*, grün, gelb]\n",
    //         'français' => "Question: Le ciel est ____ : [rouge, *bleu*, vert]\n" .
    //             "Question: Une pomme peut être ____ : [*rouge*, verte, jaune]\n",
    //         'anglais', 'en' => "Question: The sky is ____ : [red, *blue*, green]\n" .
    //             "Question: An apple can be ____ : [*red*, green, yellow]\n",
    //         'espagnol' => "Question: El cielo es ____ : [rojo, *azul*, verde]\n" .
    //             "Question: Una manzana puede ser ____ : [*roja*, verde, amarilla]\n",
    //         default => "Question: The sky is ____ : [red, *blue*, green]\n" .
    //             "Question: An apple can be ____ : [*red*, green, yellow]\n",
    //     };
    // }
}
