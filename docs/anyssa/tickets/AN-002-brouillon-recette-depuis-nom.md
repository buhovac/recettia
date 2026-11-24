---
id: AN-002
titre: Proposer un brouillon de recette quand le chef ne donne qu'un nom de plat
statut: ouvert
branche: (à créer après la fusion d'AN-001)
créé: 2026-11-24
mis-à-jour: 2026-11-24
---

## Contexte

En testant AN-001, Anyssa a constaté qu'un simple nom de plat (« gnocchis façon steak philly ») ne donne aucun ingrédient. L'analyse ne fait qu'extraire, et le chef reçoit le message `no_ingredients`. Idée : l'IA propose un brouillon de recette que le chef corrige, puis analyse.

C'est un changement de produit. Le README dit que le coût matière reflète la vraie recette du chef et que le modèle n'invente rien. **À valider par le groupe avant de commencer** (mentionné dans la PR d'AN-001).

Forme proposée, en deux étapes : un agent séparé génère un texte de recette, qui arrive dans la zone de saisie. Le chef le corrige, puis lance l'analyse existante, sans la modifier. Ça fait deux appels au lieu d'un.

## Critères d'acceptation

- [ ] Le groupe a validé l'idée (décision notée dans `docs/decisions/` si elle change le périmètre).
- [ ] Quand l'analyse renvoie `no_ingredients`, la page propose « Proposer une recette » au lieu d'un simple message d'erreur.
- [ ] Le brouillon arrive dans la zone de saisie, modifiable, avec la mention « Généré par IA ».
- [ ] Le brouillon n'est jamais analysé automatiquement : c'est le chef qui lance l'analyse.
- [ ] Chaque génération écrit une ligne dans `ai_call_logs` avec sa propre valeur `feature`.
- [ ] La génération a un plafond de jetons, et son coût mesuré est noté ici.
- [ ] Tests avec l'IA simulée, sans aucun appel réel.

## Journal du ticket

- 2026-11-24 : créé à la demande d'Anyssa ; reporté après la revue n°1.
