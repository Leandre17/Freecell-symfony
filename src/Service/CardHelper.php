<?php

namespace App\Service;

/**
 * Une carte est représentée par une simple chaîne, ex: "AS" (as de pique),
 * "10H" (10 de coeur), "KD" (roi de carreau), "2C" (2 de trèfle).
 */
class CardHelper
{
    public const SUITS = ['S', 'H', 'D', 'C'];

    public const RANK_LABELS = [
        1 => 'A', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7',
        8 => '8', 9 => '9', 10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K',
    ];

    public static function make(int $rank, string $suit): string
    {
        return self::RANK_LABELS[$rank].$suit;
    }

    public static function suitOf(string $card): string
    {
        return substr($card, -1);
    }

    public static function rankOf(string $card): int
    {
        $label = substr($card, 0, -1);

        $rank = array_search($label, self::RANK_LABELS, true);

        return $rank === false ? 0 : $rank;
    }

    public static function isRed(string $card): bool
    {
        return in_array(self::suitOf($card), ['H', 'D'], true);
    }
}
