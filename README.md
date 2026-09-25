# FreeCell — Symfony

Un jeu de FreeCell jouable dans le navigateur, avec un backend Symfony
(état de la partie géré en session, règles validées côté serveur) et une
interface simple en HTML/CSS/JS vanilla (pas de build front nécessaire).

## Installation

Prérequis : PHP 8.1+ et Composer.

```bash
cd freecell-symfony
composer install
```

## Lancer le serveur

Avec le binaire Symfony CLI (si installé) :

```bash
symfony server:start
```

Ou avec le serveur PHP intégré :

```bash
php -S localhost:8000 -t public
```

Puis ouvre http://localhost:8000 dans ton navigateur.

## Comment jouer

- Clique sur une carte visible (le dessus d'une colonne, ou une cellule
  libre) pour la sélectionner — elle se surligne en jaune.
- Clique ensuite sur une colonne, une cellule libre ou une fondation pour
  y déposer la carte sélectionnée.
- Les règles du FreeCell classique sont appliquées côté serveur :
  - Sur une colonne : alternance rouge/noir et valeur décroissante de 1.
  - Sur une fondation : même couleur (pique/cœur/carreau/trèfle), ordre
    croissant en partant de l'as.
  - Sur une cellule libre : une seule carte à la fois.
- "Nouvelle partie" redistribue et relance une partie depuis zéro.

## Structure du projet

```
config/                 Configuration Symfony (routes, services, session, twig)
public/index.php        Point d'entrée HTTP
public/css/style.css    Styles du plateau
public/js/game.js       Rendu du plateau et interactions (sélection, appels /move)
src/Kernel.php          Kernel Symfony (MicroKernelTrait)
src/Service/CardHelper.php    Utilitaires sur les cartes (rang, couleur, suite)
src/Service/FreeCellGame.php  Règles du jeu : distribution, validation des coups, victoire
src/Controller/GameController.php  Routes : / (affichage), /move (jouer un coup), /new-game
templates/               Vues Twig
```

## Pistes d'évolution

- Le bouton Undo annule le dernier coup, y compris une auto-complétion complète.
- Le bouton Auto-compléter envoie automatiquement les cartes jouables vers les fondations.
- Une suite de cartes peut être sélectionnée depuis une colonne ; le serveur vérifie
  la capacité disponible dans les cellules libres et colonnes vides.
- Les parties commencées et gagnées, ainsi que le meilleur nombre de coups, sont
  persistés dans `var/freecell.sqlite`.
