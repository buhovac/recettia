# État d'Anyssa, mis à jour le 2026-11-24

## Où j'en suis

L'analyse de recette par OpenRouter (AN-001) marche en local : six essais réels, page testée sur ordinateur, et Luna retenu après comparaison (D-001).
Il reste la largeur téléphone, puis le commit et la PR. Rien n'est encore poussé.
La revue n°1 (aujourd'hui, 18 h) exige un appel réel en production : il faut la PR fusionnée et la clé sur le serveur (Marko).

## Tickets ouverts

| Ticket | Sujet                                                    | Statut                          |
| ------ | -------------------------------------------------------- | ------------------------------- |
| AN-001 | Analyse d'une recette par OpenRouter, journal des appels | en cours                        |
| AN-002 | Brouillon de recette depuis un nom de plat               | ouvert, à valider par le groupe |

## Prochaine action

1. Vérifier la page « Analyser une recette » en largeur téléphone (F12, puis l'icône téléphone).
2. Committer sur `feature/openrouter-recipe-analysis`, pousser, ouvrir la PR et demander une relecture.
3. Après la fusion, envoyer à Marko : `OPENROUTER_API_KEY` à mettre dans `shared/.env` du serveur, puis `php artisan recipe:try` en production.
4. Créer un compte en production pour la démonstration.
