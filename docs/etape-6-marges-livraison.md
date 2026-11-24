# MYCA — Étape 6 (lot 2) : marges et ventes des boutiques (`Myca_PartnerSales`, M21)

Livré le 24/11/2026. **Point de validation : STOP.** Avec ce module, **le lot 2 est entièrement livré**. La suite (lot 3 : `Myca_Production`, M10–M11) attend votre accord.

## 1. Ce qui est livré

### Le prix de vente des boutiques (question V3 de l'étape 5, sans réponse à ce jour)

La boutique peut renseigner son prix de vente de deux façons, et aucun chiffre n'est supposé :

1. **Montant déclaré sur la commande.** Dans « Détail des ventes », la boutique indique ce que son client lui a payé HT. Ce montant est prioritaire. Il est réparti entre les articles au prorata de leur achat MYCA, au centime près.
2. **Grille de prix de la boutique.** « Ajuster mes prix de vente », comme dans la maquette : la boutique saisit son prix HT habituel par modèle. Il s'applique aux commandes sans montant déclaré. Seul le **titulaire** du compte boutique peut le modifier.
3. **Sinon, rien.** La vente est **exclue des marges** et comptée « non déclarée ».
   - Une option de configuration, **désactivée par défaut**, permet d'utiliser le prix public conseillé HT comme estimation, signalée comme telle.
   - Elle se trouve dans Magasins › Configuration › MYCA › Marges des boutiques.

Ce fonctionnement couvre les deux réponses possibles à V3 (saisie à chaque commande, ou prix par défaut) **sans rien imposer**. Il reste à valider.

### Calculs

- **Périmètre** : seules les commandes en **dropshipping** comptent. Les ventes en boutique physique ne sont pas connues de MYCA. Les quantités annulées ou remboursées sont retirées.
- **Achat MYCA d'une ligne** : prix HT après remise, **plus les frais de port de la commande répartis au prorata**.
- **Indicateurs de la période** (30 jours, 3 mois, 12 mois), comparés à la période précédente :
  - chiffre d'affaires déclaré ;
  - marge brute estimée ;
  - taux de marge (évolution en points) ;
  - quantités ;
  - panier moyen.
- **Rentabilité** : marge moyenne par article, coût d'achat total, marge brute, rentabilité (marge rapportée au coût d'achat).
- **Mention de la maquette** reprise : la marge est une estimation, **ni la marge de MYCA ni un bénéfice certain**.

### Écran M21 « Mes marges & ventes » (`/partenaire/marges`, menu partenaire et pied de page)

- **Vue d'ensemble** :
  - évolution du chiffre d'affaires sur 7 mois (courbe, infobulle au survol et au clavier, tableau équivalent pour les lecteurs d'écran) ;
  - répartition du chiffre d'affaires et taux de marge par modèle ;
  - top ventes, indicateurs de rentabilité, actions rapides.
- **Détail des ventes** : chaque commande avec son achat MYCA, sa vente, l'origine du prix, et la déclaration ou l'effacement du montant.
- **Marges par produit** : tous les modèles de la période.
- **Ajuster mes prix de vente** : la grille de prix de la boutique.
- **Export CSV** : séparateur point-virgule, encodage UTF-8, lisible dans Excel.

## 2. Fichiers

**`app/code/Myca/PartnerSales/`**
- `Model/MarginCalculator` : calculs purs (répartition, synthèse, marges par modèle, évolution mensuelle).
- `Model/SalesData` : lignes de vente de la boutique, ordre de priorité des prix, grille de prix.
- `Controller/Marges/` : page, prix, déclaration, export.
- `ViewModel/Margins`.
- `etc/` : table `myca_partner_sale_price`, configuration, ACL, route.
- Tests.

**Autres modules**
- `Myca_Partner` : rubrique « Mes marges & ventes » dans le menu et le pied de page.

**Thème**
- `Myca_PartnerSales/` : mise en page, M21, tableau des marges par modèle.

**Commandes** : `bin/magento module:enable Myca_PartnerSales`, `setup:upgrade`, `setup:di:compile`, build Tailwind, `cache:flush`.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 72 tests**. Nouveaux tests : répartition au centime, ventes non déclarées exclues, marges par modèle et par période, évolutions |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal, M21 × 4 largeurs | **Aucun** |

Contrôles dans le navigateur, avec la boutique de démonstration « Mode Élégance » :

| Scénario | Résultat |
|---|---|
| Indicateurs sur 30 jours | Le chiffre d'affaires est égal à la somme des montants déclarés (454 €), puis à 449,90 € après les modifications ci-dessous |
| Montant d'une commande effacé | Commande « Non déclarée », exclue des marges |
| Prix du modèle saisi dans la grille (54,90 € HT) | La commande reprend ce prix, avec l'origine « Votre grille de prix » |
| Vendeuse (collaboratrice) qui force l'envoi de la grille | **Refusé**, champs désactivés |
| Déclaration sur une commande d'une boutique concurrente | **« Introuvable »** |
| Export CSV | Lignes avec achat port compris (66,58 + 4,90 = 71,48 €), vente, origine du prix, marge |

Captures : `docs/etape-6-marges/` (M21 aux 4 largeurs, détail des ventes).

## 4. Écarts avec Figma et le cahier des charges

1. **« Marges par collection » et « Répartition par collection »** : remplacées par la répartition **par modèle**. L'attribut collection ne figure pas sur les articles de démonstration.
2. **Anneau multicolore** de la répartition : remplacé par des barres classées d'une seule couleur. C'est plus lisible et cela ne dépend pas d'une palette de couleurs catégorielles à valider.
3. **« Performances »** et **« Période personnalisée »** : non livrés. Les périodes proposées sont 30 jours, 3 mois et 12 mois.
4. **Export PDF** : seul le CSV est livré.
5. **« Paramètres des marges »** : la seule option (estimation au prix public) se règle dans l'administration.
6. **Ventes en boutique physique** : hors périmètre, faute de liaison avec les caisses.

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Prix de vente** (question V3 de l'étape 5) : la grille de la boutique et le montant par commande conviennent-ils ? Faut-il rendre la déclaration obligatoire au moment de la commande (M17) ? |
| V2 | **Estimation au prix public conseillé** : faut-il l'activer, ou laisser les ventes non déclarées hors des marges (réglage livré) ? |
| V3 | **Frais de la boutique** (emballage, commissions, transport client) : faut-il pouvoir les saisir pour affiner la marge ? |
| V4 | **Rôles** : une collaboratrice peut voir les marges ; faut-il les masquer selon le rôle (question V4 de l'étape 5) ? |
| V5 | Questions des étapes précédentes, en particulier la **licence Hyvä Checkout**. |
