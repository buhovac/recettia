---
id: JM-001
titre: Préparer et vérifier l'environnement local Recettia sous Windows
statut: terminé
branche: chore/bureaucratie-jean-marie
créé: 2026-11-21
mis-à-jour: 2026-11-21
---

## Contexte

Disposer d'un environnement local permettant à Jean-Marie de développer
et de vérifier Recettia avec la version du dépôt de l'équipe.
Aucune modification du code applicatif n'est prévue dans ce ticket.

## Critères d'acceptation

- [x] La branche de travail part de la version récupérée de origin/main.
- [x] Node respecte les exigences du projet.
- [x] composer install et npm ci terminent avec succès.
- [x] npm run build termine avec succès.
- [x] Pint, PHPStan, le lint frontend et les types passent séparément.
- [x] Les tests Laravel ne présentent aucun échec.
- [x] Laravel se connecte à une base PostgreSQL locale dédiée.
- [x] Les migrations prévues sont appliquées sur cette base.
- [x] L'accueil, l'inscription et le dashboard fonctionnent sur la version actuelle.
- [x] Le suivi personnel décrit les résultats et les limites restantes.

## Journal du ticket

- 2026-11-21 : ticket validé par Jean-Marie.
- Dépôt local aligné sur origin/main ; ancien commit conservé dans
  sauvegarde/initialisation-locale.
- Node utilisé : 24.22.3. Installation npm et build réussis.
- Tests Laravel : 34 réussis, 9 ignorés, 126 assertions.
- PHPStan : aucune erreur. Lint frontend et types : réussis.
- Pint : erreur interne « View [summary] not found » en sortie habituelle.
  Réinstallation de la même version sans effet.
  Contournement vérifié : vendor/bin/pint --test --format=agent,
  résultat passed et code de retour 0.
- PostgreSQL 16 démarré dans Docker, état healthy, port local 5434.
- Connexion Laravel corrigée en remplaçant DB_CONNECTION=sqlite par pgsql.
- Cinq migrations appliquées ; toutes affichées comme Ran.
- Inscription, accès au dashboard et reconnexion confirmés par Jean-Marie.
- Les résultats techniques proviennent des sorties terminal transmises.
  Le parcours navigateur est confirmé par Jean-Marie, sans observation
  directe par l'agent.
- Limite restante : composer test reste bloqué par le résumé visuel de Pint.
  Les contrôles séparés passent.
- Suivi personnel actualisé ; ticket terminé le 2026-11-21.
