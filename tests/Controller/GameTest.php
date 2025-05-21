<?php

namespace App\Tests\Controller;

use App\Entity\Child;
use App\Entity\Game;
use App\Entity\Images;
use App\Entity\Parents;
use App\Service\GameService;
use App\Service\ProgressService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class GameTest extends WebTestCase
{
    private $entityManager;
    private $progressService;

    protected function setUp(): void
    {
        // Initialize services lazily
        $this->entityManager = null;
        $this->progressService = null;
    }

    public function testGameServiceStartGame(): void
{
    // Create a session with MockArraySessionStorage
    $session = new Session(new MockArraySessionStorage());
    $session->set("game_state_child_999", [
        'currentLevel' => 1,
        'currentStage' => 1,
        'currentLevelPoints' => 0,
        'totalTriesInLevel' => 0,
        'currentStageTries' => 0,
        'currentImages' => [],
        'correctWord' => null,
        'correctImageUrl' => null,
        'highestLevelReached' => 1,
        'levelStartTime' => (new \DateTime())->getTimestamp(),
        'stagePoints' => [],
        'stageTries' => [],
    ]);

    // Boot kernel and get services
    $client = static::createClient();
    $container = self::getContainer();
    $this->entityManager = $container->get('doctrine')->getManager();
    $this->progressService = $container->get(ProgressService::class);

    // Create GameService with mocked session
    $gameService = new GameService($this->entityManager, $session);

    $this->entityManager->beginTransaction();
    try {
        // Create a parent
        $parent = new Parents();
        $parent->setParentId(999);
        $parent->setEmail('testparent_' . uniqid() . '@example.com');
        $parent->setPassword('password');
        $parent->setVerificationCode('code');
        $parent->setIsVerified('yes');
        $this->entityManager->persist($parent);

        // Create a child
        $child = new Child();
        $child->setChildId(999);
        $child->setParentId($parent);
        $child->setName('Test Child');
        $child->setAge(8);
        $child->setLanguage('French');
        $child->setAvatar('avatar.png');
        $this->entityManager->persist($child);

        // Check if game with id=3 exists; if not, create it
        $game = $this->entityManager->getRepository(Game::class)->find(3);
        $createdGame = false;
        if (!$game) {
            $game = new Game();
            $game->setId(3);
            $game->setName('Picture Game');
            $this->entityManager->persist($game);
            $createdGame = true;
        }

        // Create an image with unique ID
        $imageId = random_int(1000, 9999);
        $image = new Images();
        $image->setId($imageId);
        $image->setWord('ball');
        $image->setImage_url('ball.png');
        $image->setFrench_translation('ballon');
        $image->setSpanish_translation('pelota');
        $image->setGerman_translation('ball');
        $this->entityManager->persist($image);

        $this->entityManager->flush();

        // Set up GameService
        $gameService->setChildId(999);
        $gameService->setGameId(3);

        // Start game
        $gameService->startGame(1);
        $state = $gameService->getGameState();

        // Assert game state
        $this->assertEquals(1, $state['currentLevel']);
        $this->assertEquals(1, $state['currentStage']);
        $this->assertEquals(0, $state['currentLevelPoints']);
        $this->assertNotEmpty($state['currentImages']);
        $this->assertNotNull($state['correctWord']);

        $this->entityManager->commit();
    } catch (\Exception $e) {
        $this->entityManager->rollback();
        throw $e;
    }

    // Cleanup
    $this->entityManager->remove($image);
    if ($createdGame) {
        $this->entityManager->remove($game);
    }
    $this->entityManager->remove($child);
    $this->entityManager->remove($parent);
    $this->entityManager->flush();
}

    public function testGameControllerMainMenu(): void
{
    // Create client
    $client = static::createClient();
    $container = self::getContainer();
    $this->entityManager = $container->get('doctrine')->getManager();
    $this->progressService = $container->get(ProgressService::class);

    // Create a mock session
    $session = new Session(new MockArraySessionStorage());
    $gameState = [
        'currentLevel' => 1,
        'currentStage' => 1,
        'currentLevelPoints' => 0,
        'totalTriesInLevel' => 0,
        'currentStageTries' => 0,
        'currentImages' => [],
        'correctWord' => null,
        'correctImageUrl' => null,
        'highestLevelReached' => 1,
        'levelStartTime' => (new \DateTime())->getTimestamp(),
        'stagePoints' => [],
        'stageTries' => [],
    ];
    $session->set("game_state_child_998", $gameState);
    $container->set('session', $session);

    // Mock GameService
    $gameService = $this->createMock(GameService::class);
    $gameService->method('getGameState')->willReturn($gameState);
    $gameService->method('getHighestLevelReached')->willReturn(1);
    $gameService->method('isGameComplete')->willReturn(false);
    $gameService->expects($this->any())->method('setChildId')->willReturn(null);
    $gameService->expects($this->any())->method('setGameId')->willReturn(null);
    $container->set(GameService::class, $gameService);

    // Ensure child and parent exist
    $this->entityManager->beginTransaction();
    try {
        $this->progressService->ensureChildExists(998, 998, 8, 'Test Child', 'French');
        $this->entityManager->flush();
        $this->entityManager->commit();
    } catch (\Exception $e) {
        $this->entityManager->rollback();
        throw $e;
    }

    // Make the request
    $client->request('GET', '/main/998/998');

    // Debug response
    $response = $client->getResponse();
    error_log('MainMenu Status Code: ' . $response->getStatusCode());
    error_log('MainMenu Content: ' . substr($response->getContent(), 0, 1000));

    // Assertions
    $this->assertResponseIsSuccessful();
    $this->assertStringContainsString('لعبة مطابقة الصور', $response->getContent());
    $this->assertStringContainsString('/start/998/998/1', $response->getContent());
    $this->assertStringContainsString('بدء اللعب', $response->getContent());
}

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->entityManager !== null) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }
}