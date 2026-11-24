---
id: D-001
titre: Les appels au modèle passent par laravel/ai, fournisseur OpenRouter, modèle openai/gpt-6-luna par défaut
statut: proposée
proposée-par: anyssa
date: 2026-11-24
---
## Contexte

L'analyse de recette (O1) doit appeler un modèle de langage depuis Laravel, jamais depuis le navigateur. Le cours fournit une clé OpenRouter et recommande un SDK d'agents plutôt qu'un client HTTP écrit à la main (page « Choisir sa solution »). La revue n°1 du 24 novembre demande un appel réel depuis la production, journalisé avec son coût.

Le README prévoyait une interface `AiClient` maison avec un `FakeAiProvider` (ADR 0007). Il faut choisir avant d'écrire le premier appel.

## Décision

- Le paquet officiel `laravel/ai` (^1.4) porte tous les appels au modèle. Fournisseur par défaut : `openrouter` (`config/ai.php`), clé dans `OPENROUTER_API_KEY`, jamais dans le dépôt.
- Modèle par défaut : `openai/gpt-6-luna`, modifiable par `OPENROUTER_MODEL`. C'est l'un des deux modèles conseillés par le cours, et celui qu'il recommande quand la sortie structurée compte. Prix vérifiés le 2026-11-24 dans le catalogue OpenRouter : 0,10 $ en entrée et 0,50 $ en sortie par million de jetons. Il gère `structured_outputs` et `reasoning_effort`.
- Chaque fonction d'IA est une classe d'agent dans `app/Ai/Agents/` avec une sortie structurée (`HasStructuredOutput`, `#[Strict]`), un plafond de jetons (`#[MaxTokens]`) et un raisonnement bas.
- La réponse du modèle reste une donnée non fiable : un validateur applicatif la contrôle avant tout usage.
- Chaque appel, réussi ou raté, écrit une ligne dans `ai_call_logs` : modèle, jetons, coût facturé lu dans `usage.cost` de la réponse OpenRouter, durée, issue.
- Les tests simulent l'agent avec `Agent::fake()` et `preventStrayPrompts()`. Aucun test n'appelle OpenRouter.

## Écarté

- **Client HTTP maison vers OpenRouter (`AiClient`, ADR 0007 du README)** : plus de code à écrire et à tester, pour ce que le SDK fournit déjà (sortie structurée, erreurs typées, fausses réponses pour les tests).
- **Prism** : option 2 du cours pour Laravel, sans persistance ni classe d'agent. Le cours demande un seul SDK par projet.
- **DeepSeek V4.1 Flash** : moins cher au catalogue, mais plus cher et plus lent à l'usage. Mesure du 2026-11-24 avec `php artisan recipe:try` sur la recette de risotto du README, résultat juste et identique pour les deux modèles :

    | Modèle | Jetons entrée / sortie | Coût facturé | Durée |
    | --- | --- | --- | --- |
    | `openai/gpt-6-luna` | 533 / 203 | 0,000153 $ | 2,8 s |
    | `deepseek/deepseek-v4.1-flash` | 355 / 1 165 | 0,000816 $ | 9,1 s |

    Deuxième mesure, avec une recette aux allergènes cachés (bowl thaï : sauce soja, sauce d'huître, nuoc-mâm, tahini, mayonnaise, et les pièges pignons de pin et lait de coco) :

    | Modèle | Issue | Jetons entrée / sortie | Coût facturé | Durée |
    | --- | --- | --- | --- | --- |
    | `openai/gpt-6-luna` | succès ; 7 allergènes justes, aucun faux positif, poisson (nuoc-mâm) oublié | 611 / 959 | 0,000535 $ | 6,9 s |
    | `deepseek/deepseek-v4.1-flash` | réponse tronquée au plafond de 3 000 jetons, rejetée par le validateur | 446 / 3 000 | 0,002046 $ | 18,4 s |

    Après ajout à la consigne des allergènes cachés et des définitions UE des codes ambigus, Luna trouve sur la même recette 9 allergènes justes, sans faux positif (0,000644 $, 9,1 s). Les sulfites, qui dépendent du produit, restent au jugement du chef.

    DeepSeek a été facturé environ 3 fois son prix catalogue : OpenRouter l'a routé vers un fournisseur plus cher, comme le cours le signale. L'argument du cours sur les outils combinés à la sortie structurée ne nous concerne pas, car l'agent n'a pas d'outil.
- **Les variantes `:free`** : leur limite est partagée entre toutes les clés du professeur, et beaucoup refusent la sortie structurée.
- **OpenCode et Hermes Agent** : ce sont des agents de programmation centrés sur un dossier de code, pas des briques à intégrer dans une application web.

## Conséquences

- Budget : la clé du cours est plafonnée à 0,50 $, sans recharge, et partagée entre les postes et la production. Au coût mesuré, cela fait environ 3 200 analyses de recette courte.
- Il faut lancer `composer install` et `php artisan migrate` après la fusion (nouvelle table `ai_call_logs`). La clé se met dans le `.env` de chaque poste et du serveur.
- Sans `models.text.default`, le SDK utiliserait un modèle beaucoup plus cher : ne pas retirer ce réglage de `config/ai.php`.
- Les scénarios d'échec O5 (`AI_PROVIDER=fake`, `AI_FAKE_SCENARIO`) restent à faire. Les fausses réponses du SDK peuvent lever les exceptions typées (`InsufficientCreditsException`, `ProviderConnectionException`…), ce qui suffit pour les tests, mais pas pour une démonstration en direct depuis l'interface d'administration.
- La rétention zéro (ZDR) n'est pas activée. Le cours laisse ce choix au groupe, à justifier dans le rapport, et la restriction peut réduire les fournisseurs disponibles pour le modèle choisi. À trancher dans une décision séparée.
- Le README (sections Architecture decisions, Installation, Tech stack) est à aligner sur cette décision.
