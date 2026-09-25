<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Contient toutes les règles du FreeCell : distribution des cartes,
 * validation et application des déplacements, détection de victoire.
 *
 * L'état de la partie est un simple tableau associatif, stocké en session :
 * [
 *   'columns'     => [ [carte, carte, ...] x 8 colonnes ],
 *   'freecells'   => [carte|null, carte|null, carte|null, carte|null],
 *   'foundations' => ['S' => rang, 'H' => rang, 'D' => rang, 'C' => rang], // 0 = vide
 *   'moves'       => nombre de coups joués,
 *   'won'         => bool,
 * ]
 */
class FreeCellGame
{
    private const SESSION_KEY = 'freecell_state';
    private const NB_COLUMNS = 8;
    private const NB_FREECELLS = 4;

    public function getState(SessionInterface $session): array
    {
        if (!$session->has(self::SESSION_KEY)) {
            $this->saveState($session, $this->newGame());
        }

        $state = $session->get(self::SESSION_KEY);
        if (!array_key_exists('history', $state)) {
            $state['history'] = [];
            $this->saveState($session, $state);
        }

        return $state;
    }

    public function saveState(SessionInterface $session, array $state): void
    {
        $session->set(self::SESSION_KEY, $state);
    }

    public function newGame(): array
    {
        $deck = [];
        foreach (CardHelper::SUITS as $suit) {
            for ($rank = 1; $rank <= 13; $rank++) {
                $deck[] = CardHelper::make($rank, $suit);
            }
        }
        shuffle($deck);

        $columns = array_fill(0, self::NB_COLUMNS, []);
        foreach ($deck as $i => $card) {
            $columns[$i % self::NB_COLUMNS][] = $card;
        }

        return [
            'columns' => $columns,
            'freecells' => array_fill(0, self::NB_FREECELLS, null),
            'foundations' => ['S' => 0, 'H' => 0, 'D' => 0, 'C' => 0],
            'moves' => 0,
            'won' => false,
            'history' => [],
        ];
    }

    /**
     * Tente de jouer un coup. Lève une InvalidArgumentException si le coup
     * est illégal (message destiné à être affiché à l'utilisateur).
     */
    public function applyMove(array $state, array $from, array $to): array
    {
        return $this->performMove($state, $from, $to, true);
    }

    public function undo(array $state): array
    {
        if (empty($state['history'])) {
            throw new \InvalidArgumentException('Aucun coup à annuler.');
        }

        $history = $state['history'];
        $previous = array_pop($history);
        $previous['history'] = $history;

        return $previous;
    }

    public function autoComplete(array $state): array
    {
        $initial = $state;
        $changed = false;

        do {
            $changedThisRound = false;
            foreach ($state['freecells'] as $index => $card) {
                if ($card !== null && $this->canAutoComplete($state, $card)) {
                    $state = $this->performMove($state, ['type' => 'free', 'index' => $index], ['type' => 'foundation', 'index' => CardHelper::suitOf($card)], false);
                    $changed = $changedThisRound = true;
                }
            }
            foreach ($state['columns'] as $index => $column) {
                $card = $column[count($column) - 1] ?? null;
                if ($card !== null && $this->canAutoComplete($state, $card)) {
                    $state = $this->performMove($state, ['type' => 'column', 'index' => $index], ['type' => 'foundation', 'index' => CardHelper::suitOf($card)], false);
                    $changed = $changedThisRound = true;
                }
            }
        } while ($changedThisRound);

        if ($changed) {
            $this->pushHistory($state, $initial);
        }

        return $state;
    }

    private function performMove(array $state, array $from, array $to, bool $recordHistory): array
    {
        $count = (int) ($from['count'] ?? 1);
        $cards = $this->cardsAt($state, $from, $count);
        $this->assertCanPlace($state, $cards, $to);

        if ($recordHistory) {
            $this->pushHistory($state, $state);
        }

        $state = $this->removeCards($state, $from, $count);
        $state = $this->placeCards($state, $to, $cards);
        $state['moves'] += $count;
        $state['won'] = $this->isWon($state);

        return $state;
    }

    private function cardsAt(array $state, array $loc, int $count): array
    {
        if ($count < 1 || ($loc['type'] ?? null) === 'foundation') {
            throw new \InvalidArgumentException('Source de carte invalide.');
        }

        if ($loc['type'] === 'column') {
            $cards = $state['columns'][$loc['index']] ?? [];
            if ($count > count($cards)) {
                throw new \InvalidArgumentException("Il n'y a pas assez de cartes à cet endroit.");
            }
            $selected = array_slice($cards, -$count);
            for ($index = 1; $index < count($selected); $index++) {
                if (CardHelper::rankOf($selected[$index - 1]) !== CardHelper::rankOf($selected[$index]) + 1 || CardHelper::isRed($selected[$index - 1]) === CardHelper::isRed($selected[$index])) {
                    throw new \InvalidArgumentException('La suite sélectionnée doit alterner les couleurs et décroître.');
                }
            }

            return $selected;
        }

        if ($loc['type'] === 'free' && $count === 1 && ($state['freecells'][$loc['index']] ?? null) !== null) {
            return [$state['freecells'][$loc['index']]];
        }

        throw new \InvalidArgumentException("Il n'y a pas de carte à cet endroit.");
    }

    private function removeCards(array $state, array $loc, int $count): array
    {
        if ($loc['type'] === 'column') {
            array_splice($state['columns'][$loc['index']], -$count, $count);
        } else {
            $state['freecells'][$loc['index']] = null;
        }

        return $state;
    }

    private function placeCards(array $state, array $loc, array $cards): array
    {
        if ($loc['type'] === 'column') {
            array_push($state['columns'][$loc['index']], ...$cards);
        } elseif ($loc['type'] === 'free') {
            $state['freecells'][$loc['index']] = $cards[0];
        } else {
            $card = $cards[0];
            $state['foundations'][CardHelper::suitOf($card)] = CardHelper::rankOf($card);
        }

        return $state;
    }

    private function assertCanPlace(array $state, array $cards, array $to): void
    {
        $type = $to['type'] ?? null;
        $card = $cards[0];

        if ($type === 'column' && count($cards) > $this->moveCapacity($state, $to)) {
            throw new \InvalidArgumentException('Il faut davantage de cellules libres ou de colonnes vides pour déplacer cette suite.');
        }

        if ($type === 'free') {
            if (count($cards) !== 1) {
                throw new \InvalidArgumentException('Une seule carte peut aller dans une cellule libre.');
            }
            if (($state['freecells'][$to['index']] ?? null) !== null) {
                throw new \InvalidArgumentException('Cette cellule libre est déjà occupée.');
            }

            return;
        }

        if ($type === 'foundation') {
            if (count($cards) !== 1) {
                throw new \InvalidArgumentException('Une seule carte peut aller vers une fondation.');
            }
            $suit = CardHelper::suitOf($card);
            if ($to['index'] !== $suit) {
                throw new \InvalidArgumentException("Cette fondation n'accepte pas cette couleur de carte.");
            }
            if (CardHelper::rankOf($card) !== $state['foundations'][$suit] + 1) {
                throw new \InvalidArgumentException("Il faut poser les cartes dans l'ordre croissant, en commençant par l'as.");
            }

            return;
        }

        if ($type === 'column') {
            $col = $state['columns'][$to['index']];
            if (empty($col)) {
                return;
            }
            $target = $col[count($col) - 1];
            if (CardHelper::rankOf($target) !== CardHelper::rankOf($card) + 1) {
                throw new \InvalidArgumentException('La carte doit valoir un de moins que la carte de destination.');
            }
            if (CardHelper::isRed($target) === CardHelper::isRed($card)) {
                throw new \InvalidArgumentException('Les couleurs rouge et noir doivent alterner.');
            }

            return;
        }

        throw new \InvalidArgumentException('Destination inconnue.');
    }

    private function moveCapacity(array $state, array $to): int
    {
        $free = count(array_filter($state['freecells'], static fn (?string $card): bool => $card === null));
        $emptyColumns = count(array_filter($state['columns'], static fn (array $column): bool => $column === []));
        if (($state['columns'][$to['index']] ?? []) === []) {
            $emptyColumns = max(0, $emptyColumns - 1);
        }

        return ($free + 1) * (2 ** $emptyColumns);
    }

    private function canAutoComplete(array $state, string $card): bool
    {
        $suit = CardHelper::suitOf($card);

        return CardHelper::rankOf($card) === $state['foundations'][$suit] + 1;
    }

    private function pushHistory(array &$state, array $snapshot): void
    {
        $snapshot['history'] = [];
        $state['history'][] = $snapshot;
    }

    private function isWon(array $state): bool
    {
        foreach ($state['foundations'] as $rank) {
            if ($rank !== 13) {
                return false;
            }
        }

        return true;
    }
}
