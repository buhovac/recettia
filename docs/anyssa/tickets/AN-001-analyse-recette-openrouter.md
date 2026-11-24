---
id: AN-001
titre: Analyse d'une recette par OpenRouter (laravel/ai), avec journal des appels
statut: en cours
branche: feature/openrouter-recipe-analysis
créé: 2026-11-24
mis-à-jour: 2026-11-24
---

## Contexte

Cœur de O1 : le chef colle une recette, le modèle renvoie ingrédients, quantités et allergènes en JSON strict, et le code valide la réponse. Livrable de la revue n°1 (24 novembre, 18 h) : un appel réel depuis la production, journalisé avec son coût (O4). Hors périmètre : tables `Dish` et `Ingredient`, coût matière, scénarios O5 depuis l'administration. Décision liée : D-001.

## Critères d'acceptation

- [x] `laravel/ai` est installé, OpenRouter est le fournisseur par défaut et le modèle se choisit par `OPENROUTER_MODEL` (défaut `openai/gpt-6-luna`).
- [x] L'agent `RecipeAnalysisAgent` impose un schéma strict : unités et allergènes en listes fermées, aucun prix.
- [x] Le validateur rejette les réponses qui enfreignent une règle bloquante du README et convertit kg, cl et l en g et ml (tests unitaires).
- [x] Chaque appel écrit une ligne dans `ai_call_logs`, qu'il réussisse ou échoue (tests).
- [x] Un timeout, un crédit épuisé, une panne ou une réponse invalide affichent un message clair en français, sans trace technique (tests).
- [x] Une recette vide, trop courte ou de plus de 5 000 caractères est refusée sans appel au modèle (tests).
- [x] Aucun test n'appelle OpenRouter (`preventStrayPrompts`).
- [x] Pint, PHPStan, Pest, `vp check`, vue-tsc et le build passent en local.
- [x] Un appel réel en local réussit, et son coût est noté ici.
- [x] La page « Analyser une recette » fonctionne à l'écran sur ordinateur : résultat, avertissement, bouton pendant l'analyse (vérifié par Anyssa).
- [ ] La page a été vérifiée en largeur téléphone.
- [x] Un texte sans ingrédient (par exemple un nom de plat seul) donne un message spécifique (`no_ingredients`), pas le message de panne (tests).
- [ ] La PR est relue et fusionnée.
- [ ] Un appel réel depuis la production apparaît dans `ai_call_logs` avec son coût.

## Journal du ticket

- 2026-11-24 : créé ; plan validé par Anyssa.
- 2026-11-24 : code et tests écrits ; vérifications locales vertes (67 tests, dont 58 réussis et 9 ignorés, ceux du kit de démarrage). L'essai réel reste à faire par Anyssa avec sa clé.
- 2026-11-24 : essais réels lancés par Anyssa avec `recipe:try` (sorties transmises à l'agent). Luna : succès, 533 / 203 jetons, 0,00015325 $, 2,8 s. DeepSeek V4.1 Flash : succès, 355 / 1 165 jetons, 0,00081645 $, 9,1 s. Même résultat juste pour les deux. Luna est retenu (D-001). La clé est plafonnée à 0,50 $ sans recharge.
- 2026-11-24 : recette aux allergènes cachés (bowl thaï). Luna : succès, 0,00053519 $, 6,9 s, 959 jetons en sortie, **poisson (nuoc-mâm) oublié**, pièges évités. DeepSeek : réponse tronquée à 3 000 jetons et rejetée, 0,0020463 $, 18,4 s. Le plafond de 3 000 jetons est à surveiller pour les longues recettes. Total des essais : environ 0,0036 $.
- 2026-11-24 : consigne de l'agent complétée pour les allergènes cachés dans les produits transformés. Nouvel essai de Luna sur le bowl thaï : poisson et gluten trouvés, mais faux positif « fruits à coque » sur les pignons de pin (0,00060479 $, 8,6 s).
- 2026-11-24 : définitions UE ajoutées à la consigne (nuts, gluten, milk, sulphites). Nouvel essai : 9 allergènes justes, aucun faux positif, seuls les sulfites manquent (cas ambigu). 787 / 1 143 jetons, 0,0006437 $, 9,1 s. Un seul essai par version de la consigne : c'est une tendance, pas une mesure.
- 2026-11-24 : à prévoir dans un autre ticket : un jeu de recettes de référence avec leurs allergènes attendus. Le rapprochement avec la liste de prix devra ignorer accents et apostrophes, car le modèle réécrit les noms (« celeri » devient « céleri », l'apostrophe ' devient ’).
- 2026-11-24 : total dépensé pendant les essais : environ 0,0048 $ sur 0,50 $.
- 2026-11-24 : page testée par Anyssa sur ordinateur avec une vraie recette (gnocchis façon steak philly). Allergènes justes : la sauce Worcestershire donne poisson et gluten, le bouillon donne céleri. Un nom de plat seul renvoyait le message de panne, d'où l'ajout de l'issue `no_ingredients`. La génération d'une recette depuis un nom de plat est reportée à AN-002.
- 2026-11-24 : risque accepté par Anyssa : sans quota par utilisateur, un compte peut épuiser la clé (0,50 $). La limite de la clé empêche de dépasser ce montant, et le site affiche alors « service indisponible ». Les quotas viendront avec O4.
