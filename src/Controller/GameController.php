<?php

namespace App\Controller;

use App\Service\FreeCellGame;
use App\Service\StatsStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GameController extends AbstractController
{
    public function __construct(
        private readonly FreeCellGame $game,
        private readonly StatsStore $stats,
    )
    {
    }

    #[Route('/', name: 'game_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$request->getSession()->has('freecell_state')) {
            $this->game->saveState($request->getSession(), $this->game->newGame());
            $this->stats->recordGameStarted();
        }
        $state = $this->game->getState($request->getSession());

        return $this->render('game/index.html.twig', ['state' => $state, 'stats' => $this->stats->getStats()]);
    }

    #[Route('/new-game', name: 'game_new', methods: ['POST'])]
    public function newGame(Request $request): Response
    {
        $this->game->saveState($request->getSession(), $this->game->newGame());
        $this->stats->recordGameStarted();

        return $this->redirectToRoute('game_index');
    }

    #[Route('/move', name: 'game_move', methods: ['POST'])]
    public function move(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $state = $this->game->getState($request->getSession());
        $wasWon = $state['won'];

        try {
            $state = $this->game->applyMove($state, $payload['from'] ?? [], $payload['to'] ?? []);
            $this->game->saveState($request->getSession(), $state);
            $this->recordWin($wasWon, $state);

            return new JsonResponse(['success' => true, 'state' => $state]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage(), 'state' => $state]);
        }
    }

    #[Route('/undo', name: 'game_undo', methods: ['POST'])]
    public function undo(Request $request): JsonResponse
    {
        $state = $this->game->getState($request->getSession());

        try {
            $state = $this->game->undo($state);
            $this->game->saveState($request->getSession(), $state);

            return new JsonResponse(['success' => true, 'state' => $state]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage(), 'state' => $state]);
        }
    }

    #[Route('/auto-complete', name: 'game_auto_complete', methods: ['POST'])]
    public function autoComplete(Request $request): JsonResponse
    {
        $state = $this->game->getState($request->getSession());
        $wasWon = $state['won'];
        $state = $this->game->autoComplete($state);
        $this->game->saveState($request->getSession(), $state);
        $this->recordWin($wasWon, $state);

        return new JsonResponse(['success' => true, 'state' => $state]);
    }

    private function recordWin(bool $wasWon, array $state): void
    {
        if (!$wasWon && $state['won']) {
            $this->stats->recordGameWon($state['moves']);
        }
    }
}
