# MYCA — Lot de conformité 1 : pages publiques et juridiques, cookies, SEO

Livré le 25/11/2026. Ce lot est le premier point du plan de l'audit (`audit-conformite-cahier-des-charges.md`, §7).

## 1. Pages publiées

Toutes les pages sont au format Page Builder et se modifient dans **Contenu › Pages**. Le patch `Myca_Content::CreateServicePages` les crée sans jamais écraser une page existante.

| Adresse | Maquette | Contenu |
|---|---|---|
| `/aide` | M41 | Centre d'aide : 8 cartes vers suivi, retours, livraison, paiement, FAQ, passeport, contact, espace pro |
| `/faq` | M46 | 8 catégories, accordéons accessibles au clavier, **recherche instantanée**, renvoi vers le contact |
| `/livraison` | — | Modes, règle du relais offert dès 100 €, **tableau des destinations lu dans la configuration** (widget « MYCA – Destinations desservies ») |
| `/retours-echanges` | M52 | **Délais lus dans la configuration** (widget « MYCA – Délais de retour » : 14 jours légaux, 30 jours seulement si activés, garantie), démarche avec ou sans compte, cas des boutiques partenaires |
| `/nos-engagements` | M49 | Engagements vérifiables uniquement : aucun « 48 h », « 30 jours » ni « toute l'Europe » |
| `/notre-histoire`, `/qualite-matieres` | — | Textes repris de l'accueil, sans promesse non certifiée |
| `/passeport-du-vetement` | — | Explication et **formulaire de vérification d'un numéro**. `/passeport` y redirige (l'adresse `passeport` reste réservée aux QR) |
| `/cgv` | M44 | CGV B2C : prix, commande, paiement, livraison, **rétractation de 14 jours avec formulaire type**, garanties légales, fidélité, médiation |
| `/cgu` | M43 | Compte, espace partenaire, politique d'avis, propriété intellectuelle |
| `/confidentialite` | — | Tableau finalités / bases légales / durées, destinataires (dont les boutiques partenaires), droits, CNIL |
| `/cookies` | — | Catégories et bouton « Modifier mes choix de cookies » |
| `/mentions-legales` | M48 | Éditeur, hébergeur, propriété intellectuelle |
| `/plan-du-site` | M51 | **Page dynamique** (`Myca_Content`) : catégories actives, pages publiées, services, pages légales. Une page désactivée en disparaît d'elle-même |
| Page 404 | — | « Page introuvable » aux couleurs MYCA, avec une recherche et des liens |

**Pages d'exemple de Magento** (en anglais) :
- « Enable cookies » : traduite ;
- « Privacy and Cookie Policy » : supprimée et redirigée en 301 vers `/confidentialite`.

**Données à fournir.** Les valeurs inconnues apparaissent sur le site **surlignées en jaune** sous la forme `[À COMPLÉTER : …]` :
- raison sociale, siège, RCS, SIRET, TVA, directeur de publication ;
- hébergeur, médiateur de la consommation ;
- frais de retour, délais de livraison, barème de fidélité ;
- sous-traitants, durées de conservation, outils de mesure.

Les textes juridiques sont des **modèles à faire valider par votre conseil juridique**. La mention correspondante est à retirer après validation.

## 2. Pieds de page

- **Pied de page public : 23 liens sur 23 répondent** (contrôle automatique). Un lien « Centre d'aide » a été ajouté.
- **Espace partenaire** : le lien « CGV Partenaire » (404) mène désormais aux conditions de vente professionnelles.
- **« Gérer les cookies »** : présent dans les trois pieds de page (public, client, partenaire).
- **Réseaux sociaux** : cliquables dès que leur adresse est saisie dans **Magasins › Configuration › MYCA › Site public › Pied de page**. Sans adresse, l'icône reste visible mais inactive.
- **Moyens de paiement** : les logos du pied de page, le bandeau de réassurance et la mention sous « Passer la commande » affichent uniquement les moyens saisis dans ce même écran. Tant qu'aucun prestataire n'est branché, le site indique « Prestataire de paiement agréé » au lieu de « CB, PayPal, Apple Pay ». La même correction a été faite sur l'accueil.

## 3. Consentement aux cookies (`Myca_Core`)

- **Bandeau** :
  - « Tout refuser » et « Tout accepter » ont le même poids visuel ;
  - « Personnaliser » propose les catégories mesure d'audience et marketing, les cookies nécessaires étant toujours actifs.
- **Choix** :
  - conservé 180 jours ;
  - redemandé quand la **version de la politique** change (réglage dans **MYCA › Site public › Consentement aux cookies**).
- **Preuve** : table `myca_cookie_consent`, avec un identifiant aléatoire, le choix, la version et la date, sans IP ni navigateur.
- **Traceurs** : un traceur ajouté plus tard se déclare ainsi, et ne s'exécute qu'après accord :
  ```html
  <script type="text/plain" data-myca-consent="analytics">…</script>
  ```
- **API JavaScript** : `window.mycaConsent.has('analytics')`, et l'événement `myca:consent`.

## 4. Référencement

- **Sitemap** : `sitemap.xml` déclaré, régénéré chaque jour par le cron et annoncé dans `robots.txt`.
- **`robots.txt`** : écarte le compte, le panier, le tunnel, la recherche interne, l'espace partenaire, les retours, les devis, la fidélité et la candidature.
- **Balise `noindex`** : posée sur le compte client, le panier, le tunnel, la recherche interne et l'espace partenaire.

## 5. Vérifications

- **Tests unitaires** : 88 réussis, dont le consentement : refus total, acceptation, choix personnalisé, identifiant falsifié refusé.
- **PHPStan niveau 8** : 0 erreur.
- **PHPCS** : 0 erreur.
- **Navigateur** :
  - refus : aucun traceur n'est déclenché ;
  - choix « mesure d'audience » : le traceur de test s'exécute ;
  - réouverture du bandeau par le pied de page ;
  - preuves enregistrées en base ;
  - recherche FAQ « point relais » : 1 réponse trouvée et ouverte ;
  - « zzzz » : message avec lien vers le contact ;
  - plan du site : 30 liens.
- **Affichage** : aucun débordement horizontal à 375 et 1280 px sur les pages du lot.
- **Captures** : `docs/lot-conformite-1/`.

**Correction pendant le lot** : la redirection de l'ancienne politique avait effacé l'adresse `/devenir-partenaire`, car `UrlPersist::replace()` supprime toutes les réécritures « custom ». La redirection est maintenant écrite ligne à ligne, et les deux adresses fonctionnent.

## 6. Points à valider

1. **Données réelles et textes juridiques** : les compléter et les faire valider. Le cahier interdit toute publication avant cette étape.
2. **Moyens de paiement** à afficher, une fois le prestataire choisi.
3. **Adresses des réseaux sociaux.**
4. **Outils de mesure ou de publicité** prévus (Google Analytics, Meta…), à lister dans la politique cookies.
5. **Bouton de rétractation en ligne** : la directive (UE) 2023/2673 l'impose depuis le 19 juin 2026. Le portail de retour existe, mais une fonction intitulée explicitement « se rétracter du contrat » est à ajouter au lot RGPD et compte client (point 5 de l'audit).
