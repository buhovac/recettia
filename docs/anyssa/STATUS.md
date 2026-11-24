# État d'Anyssa, mis à jour le 2026-11-24

## Où j'en suis

AN-001 est terminé côté code : la PR #7 est ouverte et sa CI est verte. L'analyse a été testée en local, sur PostgreSQL, avec six appels réels.
La PR attend une relecture et sa fusion. Ensuite, Marko met la clé dans `shared/.env` sur le serveur et lance `php artisan recipe:try` en production.
Le message au groupe est envoyé sur Teams : découpage du calcul du coût en 5 morceaux, et proposition de tests sur PostgreSQL dans la CI.

## Tickets ouverts

| Ticket | Sujet                                                    | Statut                          |
| ------ | -------------------------------------------------------- | ------------------------------- |
| AN-001 | Analyse d'une recette par OpenRouter, journal des appels | en cours (PR #7)                |
| AN-002 | Brouillon de recette depuis un nom de plat               | ouvert, à valider par le groupe |

## Prochaine action

1. Vérifier sur GitHub si la PR #7 est fusionnée, puis vérifier l'appel réel en production (`php artisan recipe:try` sur le serveur, par Marko).
2. Pour la démonstration : créer un compte sur le site en production et lancer une analyse.
3. Lire les réponses du groupe sur Teams : qui prend quel morceau du calcul du coût, D-002 (schéma), tests sur PostgreSQL, AN-002, ZDR.
4. Proposer une PR pour le skill `ma-stack` et le tableau « Commandes » d'`AGENTS.md` (ils sont encore vides).
