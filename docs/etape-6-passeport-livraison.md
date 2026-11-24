# MYCA — Étape 6 (lot 2) : passeport du vêtement (`Myca_Passport`, M50)

Livré le 24/11/2026. **Point de validation : STOP.** Il reste un module du lot 2 : `Myca_PartnerSales` (M21). Il attend la réponse sur le prix de vente déclaré (V3 de l'étape 5).

## 1. Ce qui est livré

### Règles (cahier des charges « Passeport du vêtement » et « Certificat d'authenticité »)

**Lot de fabrication.** Il porte une référence persistante, rattachée au modèle (produit). Ses données sont la collection, la référence modèle, la coupe, la matière, le grammage, la date de fabrication et l'origine. Chacune a un statut :
- **certifiée par MYCA** ;
- **déclarée par le fabricant** ;
- **en cours de validation**. Dans ce cas, la valeur n'est **jamais affichée**, seule la mention l'est. Les « à vérifier fabricant » de la maquette ne sont donc jamais présentés comme des faits.

**Passeports.** Deux façons de les créer :
- **un par variante** (taille, couleur) du lot : la génération ne crée jamais de doublon ;
- **à l'unité**, numérotés (LOT-…-0001).

Chaque passeport a un **identifiant aléatoire de 100 bits**, non prédictible et lisible sur une étiquette (sans I, L, O ni U). Il sert à la fois d'adresse du QR (`/passeport/IDENTIFIANT`) et de **numéro d'authenticité** (MYCA-XXXX-XXXX-…). Les saisies « myca-… », en minuscules, avec tirets ou par URL sont reconnues.

**QR code** : en SVG, téléchargeable depuis l'administration pour l'impression des étiquettes. Il utilise la bibliothèque `endroid/qr-code`, déjà installée avec Magento ; aucune dépendance n'a été ajoutée.

**Certificat d'authenticité.** Il est **désactivé par défaut**, à n'activer que lorsque l'attribution et la vérification sont opérationnelles. Une fois activé, il n'apparaît que pour un passeport **actif** dont la **collection et la référence modèle sont certifiées**. La page rappelle qu'une image du certificat ne prouve rien seule : c'est la page du QR qui fait foi.

**Révocation et remplacement.** Un passeport n'est **jamais supprimé** :
- **révoqué** : la page affiche « révoqué le … », avec le motif, et ne montre plus de certificat ;
- **remplacé** (étiquette abîmée) : l'ancien QR **renvoie automatiquement** (301) vers le nouveau passeport.

**Corrections tracées.** Toute modification d'une donnée ou d'un statut exige un **motif**. Elle est enregistrée avec la valeur et le statut avant et après, l'auteur et la date.

**Copies d'étiquettes.**
- Chaque consultation est comptée avec une **empreinte anonyme** du visiteur (HMAC de l'IP avec la clé de l'installation) : **aucune IP n'est conservée**.
- Au-delà d'un seuil de visiteurs distincts sur 24 h (25, configurable), la page invite à vérifier l'article auprès de MYCA, et le support voit l'alerte.

**Support.** L'outil « Vérifier un passeport » affiche le statut, l'article, le lot et les consultations, **sans aucune donnée personnelle**.

**Données personnelles.** La page publique n'affiche aucun propriétaire et n'est pas indexée (`NOINDEX`).

### Écrans

**M50 « Passeport du vêtement »** (`/passeport/IDENTIFIANT`, page publique du QR). On y trouve :
- le fil d'Ariane ;
- l'en-tête du modèle (collection, nom en italique) et les onglets ;
- la carte « Passeport du vêtement — MYCA » (données avec leur statut, QR, numéro) ;
- le certificat, quand il est actif ;
- les mentions réglementaires et les 5 engagements de la maquette ;
- le bouton « Ajouter à mes passeports ».

**« Mes passeports MYCA »** (espace client, menu après « Mes avis & notes ») : ajout par numéro ou depuis la page du QR, liste avec statut, accès au passeport, retrait.

**Administration** :
- Catalogue › Inventaire › **Passeports du vêtement** : grille des lots et fiche du lot (données et statuts, génération, QR, révocation, remplacement, historique) ;
- **Vérifier un passeport** ;
- droits séparés : créer et corriger, révoquer et remplacer, vérifier.

## 2. Fichiers

**`app/code/Myca/Passport/`**
- `Model/PassportManagement` : lots, passeports, historique, consultations, rattachement au client.
- `Model/PassportToken` : identifiant, saisie, format.
- `Model/LotFields` : statuts des données, conditions du certificat.
- `Model/QrCode` : QR en SVG.
- `Controller/Router` : adresse courte `/passeport/IDENTIFIANT`.
- Contrôleurs vitrine et administration, `ViewModel/`, grille, gabarits d'administration.
- `etc/` : 5 tables, configuration, ACL, menu, routes.
- Tests.

**Thème**
- `Myca_Passport/` : M50, « Mes passeports », lien de menu.
- Icône `leaf` (« Confort & liberté »), redessinée au même trait car non exportable seule.

**Commandes** : `bin/magento module:enable Myca_Passport`, `setup:upgrade`, `setup:di:compile`, build Tailwind, `cache:flush`.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 68 tests**. Nouveaux tests : 2 000 identifiants uniques et valides, saisies normalisées, données en cours de validation jamais publiées, conditions du certificat |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal : M50 et « Mes passeports », 4 largeurs | **Aucun** |

Contrôles sur l'environnement local, avec un lot de démonstration « Polo Hidalgo » (`var/tmp/myca-demo/passport*.php`, hors dépôt). Le certificat a été activé **en local seulement** pour la capture.

| Scénario | Résultat |
|---|---|
| Génération par variante, puis rejouée | 10 passeports, puis 0 |
| Page du passeport ; identifiant inconnu | 200 ; **404** |
| Données en cours de validation (matière, origine) | Valeur masquée, mention « En cours de validation » |
| Certificat : option désactivée, puis activée | Absent ; affiché |
| Ancien QR d'un passeport remplacé | **301** vers le nouveau passeport, avec un message |
| Passeport révoqué ; seconde révocation | Motif affiché, sans certificat ; refusée |
| Correction sans motif, puis avec motif | Refusée ; tracée (« À confirmer (pending) → Portugal (declared) ») |
| 30 visiteurs distincts en 24 h | Alerte sur la page et chez le support ; **0 IP en clair** |
| Client : ajout par le bouton, par numéro en minuscules ; code inconnu | 2 passeports enregistrés ; « code inconnu » |
| Support : vérification par numéro | Statut, article, lot, consultations, sans donnée personnelle |

Captures : `docs/etape-6-passeport/`. M50 et « Mes passeports » sont aux 4 largeurs ; M50 sans certificat est à 1280 px.

## 4. Écarts avec Figma et le cahier des charges

1. **Onglet « Matières & entretien »** de la maquette : non affiché sur la page du passeport. Les données d'entretien (fiche fabricant) ne sont pas fournies.
2. **Lien vers le passeport depuis la fiche produit** : non ajouté. Le passeport est propre à un lot et une étiquette, pas au modèle ; l'accès se fait par le QR.
3. **Rattachement automatique à l'achat** : le lot réellement expédié n'est pas suivi par la logistique, donc le client ajoute lui-même son passeport (QR ou numéro).
4. **Données de démonstration** : produit, matière et origine de la maquette sont fictifs. Le lot « LOT-DEMO » n'existe qu'en local.
5. **Interface d'API** (« routes et API documentées ») : les routes de la vitrine et de l'administration sont livrées, une API REST ne l'est pas. À prévoir si un outil externe (étiqueteuse, ERP) doit créer les lots.
6. **Notifications** : aucune alerte e-mail au support en cas de consultations anormales (pas de serveur de messagerie local).

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Certificat** : qui attribue et vérifie les numéros ? À partir de quand l'activer ? |
| V2 | **Granularité** : un passeport par variante et par lot (étiquette commune), ou un passeport unique par vêtement (numéro de série sur chaque étiquette) ? |
| V3 | **Données fabricant** à fournir pour chaque lot (matière, grammage, origine, entretien), et qui les certifie. |
| V4 | **Seuil d'alerte** de consultations (25 visiteurs distincts sur 24 h par défaut) et procédure du support en cas de copie. |
| V5 | Faut-il une **API** pour créer les lots depuis la production (`Myca_Production`, lot 3) ou l'étiqueteuse ? |
| V6 | Questions des étapes précédentes, en particulier la **licence Hyvä Checkout**. |
