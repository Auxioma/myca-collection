# MYCA — Lot 3 : production et contrôle qualité (`Myca_Production`, M10–M11)

Livré le 24/11/2026. **Point de validation : STOP.** Avec ce module, **tous les modules prévus par theme.md sont livrés**. Restent ouverts les points à valider des étapes précédentes, dont la **licence Hyvä Checkout**.

M10 et M11 sont des écrans d'administration. Comme le demande theme.md, ce sont des **grilles et formulaires natifs** de l'administration Magento : leur contenu suit la maquette, pas leur visuel.

## 1. Ce qui est livré

### Règles (cahier des charges « Production et fournisseurs » et « Contrôle qualité »)

**Fournisseurs et usines** : nom, atelier, pays, contact, MOQ par couleur par défaut, actif ou non.

**Ordres de production** (PRO-AAAA-NNNN). Un ordre porte sur un modèle chez un fournisseur. Il contient :
- une ligne par variante, avec la couleur et la taille lues dans le catalogue ;
- une saison, une date de livraison prévue ;
- un **prix usine HT par pièce** et des **frais (transport, douane…)** saisis à part.

Le **coût rendu entrepôt** par pièce acceptée est calculé séparément du prix usine, comme le demande le cahier.

**Prévisionnel, engagé, reçu.**
- **Prévisionnel** : les quantités, le MOQ et le prix usine restent modifiables.
- **Engagé** : ces données sont figées. L'engagement est **refusé** tant que manque l'une de ces conditions :
  - des quantités ;
  - un prix usine ;
  - des **tolérances validées** ;
  - le **MOQ par couleur** respecté.
- **En fabrication**, puis **réception en cours** et **reçu**.
- **Annulé** : seulement avant la fabrication, et avec un motif.

**Tolérances validées avant fabrication** : tolérance dimensionnelle (±cm) et nombre maximal de défauts critiques, majeurs et mineurs. Elles sont figées à l'engagement.

**Jalons usine** : échantillon, patronage, broderie, couleurs, qualité (pré-série), lancement industriel. Chacun peut être validé ou refusé, avec un commentaire, le nom de l'auteur et la date.

**Réceptions partielles et contrôle qualité**, par lot. Chaque réception enregistre :
- le numéro de lot, la date et le contrôleur ;
- les quantités reçues par variante, avec l'**écart** par rapport aux quantités engagées ;
- la **checklist** : matière, couleur, dimensions, logos, broderies, étiquettes, emballages ;
- l'échantillon contrôlé et les défauts par gravité ;
- les **photos**, stockées hors du dossier public et visibles par les seuls administrateurs ;
- le rapport et l'**action corrective**, obligatoire hors acceptation.

**Décision**
- **Accepté** : exige une checklist entièrement conforme et des défauts dans les seuils validés. Un défaut critique au-delà du seuil impose le refus.
- **Seules les pièces acceptées entrent dans le stock vendable.**
- **Quarantaine et refus restent hors stock** : la vente est bloquée.
- **Levée de quarantaine** : après un nouveau contrôle, avec un motif. Elle accepte (mise en stock) ou refuse le lot, une seule fois.

**Traçabilité** : fournisseur, lot, date, contrôleur, décision et action corrective sont conservés. Chaque action est historisée avec son auteur.

**Fiches techniques (M11)**, par modèle :
- référence modèle, distincte de l'identifiant porté par le QR du passeport ;
- **composition en pourcentages**, refusée si le total ne fait pas 100 % ;
- grammage, coupe, entretien ;
- **grille de mesures par taille** ;
- tests qualité.

### Écrans (administration › Catalogue › Production MYCA)

| Écran | Contenu |
|---|---|
| M10 « Ordres de production » | Indicateurs : ordres, pièces prévues, en fabrication, acceptées, **en quarantaine**, prochaine livraison. Grille avec filtres par fournisseur, statut et saison |
| M10 Fiche d'un ordre | Informations et coûts, répartition tailles × couleurs (prévu ou engagé, reçu, accepté, quarantaine, refusé, écart), alertes MOQ, tolérances, jalons, statut et blocages expliqués, réceptions et contrôle qualité, historique |
| Fournisseurs | Grille et fiche |
| M11 « Fiches techniques » | Choix du modèle et fiche technique |

**Droits séparés** : consulter les ordres ; engager ou annuler ; réceptions et qualité ; fournisseurs ; fiches techniques.

## 2. Fichiers

**`app/code/Myca/Production/`**
- `Model/ProductionRules` : statuts, MOQ, blocages d'engagement, décisions qualité autorisées, coût rendu, saisie des compositions et des mesures.
- `Model/ProductionManagement` : seul point d'écriture, mise en stock via MSI.
- `Model/TechpackManagement`.
- `Controller/Adminhtml/` : ordres, fournisseurs, fiches techniques.
- `ViewModel/Production`, grilles, gabarits d'administration.
- `etc/` : 8 tables, ACL, menu, routes.
- Tests.

**Commandes** : `bin/magento module:enable Myca_Production`, `setup:upgrade`, `setup:di:compile`, `cache:flush`.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 78 tests**. Nouveaux tests : MOQ par couleur, blocages d'engagement, statuts, décision selon checklist et seuils, coût rendu, composition et grille de mesures |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |

Scénario complet avec les vrais services Magento (`var/tmp/myca-demo/production.php` et `techpack.php`, hors dépôt), sur le Polo Hidalgo de démonstration :

| Scénario | Résultat |
|---|---|
| Création de l'ordre | 10 variantes, couleurs Blanc et Rouge, tailles lues dans le catalogue |
| Engagement sans tolérances validées | **Refusé**, avec la raison |
| Modification après engagement | **Refusée** |
| Réception avant lancement de la fabrication | **Refusée** |
| « Accepté » avec 1 défaut critique, puis avec une étiquette non conforme | **Refusé** dans les deux cas |
| Quarantaine sans action corrective | **Refusée** |
| Lot accepté (20 pièces) | Stock **100 → 120** |
| Lot en quarantaine (20 pièces) | Stock **inchangé (100)** : vente bloquée |
| Levée de quarantaine, puis seconde levée | Stock **120** ; seconde levée **refusée** |
| Coût rendu entrepôt | 16 € usine + 120 € de frais sur 40 pièces = **19 € par pièce** |
| Photo de contrôle envoyée depuis l'administration | Enregistrée dans `var/`, servie à l'administrateur ; **sans session, page de connexion** |
| Fiche technique avec une composition à 90 % | **Refusée** ; une fiche valide est enregistrée et relue à l'identique |

Captures : `docs/etape-7-production/` (fiche d'un ordre).

## 4. Écarts avec Figma et le cahier des charges

1. **Visuel** : écrans natifs de l'administration, comme le prévoit theme.md pour M01–M15. Les graphiques de M10 (production par collection, évolution) sont remplacés par les indicateurs chiffrés.
2. **Collections** (filtre et colonne de M10) : l'attribut collection n'existe pas sur les produits de démonstration. Les ordres se filtrent par saison, fournisseur et statut.
3. **Automatisations, multilingue, paramètres de plateforme (M11)** : non livrés. Ce sont des réglages Magento existants, ou des sujets hors de ce module.
4. **Passeport** : le numéro de lot de la réception n'est pas encore relié automatiquement à un lot `Myca_Passport` (V3).
5. **Documents** (bon de commande PDF à l'usine, export) : non livrés.
6. **Échantillonnage** : l'échantillon est saisi librement. Le plan d'échantillonnage (table AQL) n'est pas imposé (V2).

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **MOQ** : par couleur (livré) ou aussi par taille ? Faut-il un MOQ propre à chaque modèle ? |
| V2 | **Plan d'échantillonnage** : faut-il imposer une norme (AQL 2,5 par exemple) pour la taille de l'échantillon ? |
| V3 | **Passeport** : créer automatiquement le lot du passeport à la réception acceptée ? |
| V4 | **Stock** : une seule source (« default ») suffit-elle, ou faut-il plusieurs entrepôts ? |
| V5 | Bon de commande PDF à envoyer à l'usine : mentions et format. |
| V6 | Questions des étapes précédentes, en particulier la **licence Hyvä Checkout**. |
