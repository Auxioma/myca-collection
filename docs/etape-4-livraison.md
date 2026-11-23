# MYCA — Étape 4 : espace client B2C (M28–M39)

Livré le 23/11/2026. **Point de validation : STOP.** L'étape 5 (`Myca_Partner`, `Myca_Dropship`, espace partenaire) attend votre accord.
Rappel : le tunnel de commande (P07–P10) reste bloqué par la licence Hyvä Checkout (étape 3).

## 1. Ce qui est livré

Toutes les pages du compte client utilisent l'**identité client** (vert foncé, accent or), appliquée automatiquement au client connecté :

- **En-tête dédié** : logo, « Espace Client MYCA », aide, menu du compte (nom, statut, profil, retour à la boutique, déconnexion).
- **Menu latéral** : icônes Figma, rubrique active en or. En mobile, il devient un menu déroulant au-dessus du contenu.
- **Bandeau** avec photo et titre serif, et un **pied de page** dédié.

| Écran | Page | Contenu |
|---|---|---|
| M28 | Tableau de bord | Indicateurs (commandes, en cours, favoris, avis, statut), dernière commande, suivi en 4 étapes, encart collections, commandes des 30 derniers jours, favoris, avantages, profil |
| M29 | Mes commandes | Indicateurs (total, en cours, livrées, montant), filtres par statut, historique, détail de la commande choisie (articles, livraison, paiement, suivi, totaux, facture, support) |
| M29 | Détail d'une commande | Page Magento traduite, dans le cadre MYCA |
| M31 | Mes favoris | Liste de souhaits Magento, dans le cadre MYCA |
| M32 | Mes avis & notes | Avis Magento |
| M33 | Mon profil | Formulaire en carte, traduit |
| M34 | Mes adresses | Adresses par défaut et carnet, en cartes |
| M35 | Mes moyens de paiement | Lien affiché quand le module de paiement propose l'enregistrement de cartes |
| M37 | Mes paramètres | Abonnement à la newsletter |
| M39 | Se déconnecter | Lien du menu |

**Mobile** : menu en liste déroulante, tableaux de commandes transformés en cartes, indicateurs sur 2 colonnes, aucun défilement horizontal.

## 2. Fichiers créés ou modifiés

**`app/code/Myca/Core/`**
- `ViewModel/AccountOverview.php` : commandes du client, commandes en cours, favoris, avis, statut, vignettes.
- `ViewModel/OrderProgress.php` : libellé MYCA de chaque commande (En attente, En préparation, Expédiée, Livrée, Annulée, Remboursée) et étapes de suivi avec dates réelles.
- Patchs :
  - `AddDeliveredOrderStatus` : nouveau statut « Livrée », Magento s'arrêtant à « Terminée » ;
  - `SetFrenchOrderStatusLabels` : libellés français des statuts ;
  - `RenameRetailCustomerGroup` : le groupe « General » devient « Client MYCA ».
- `Test/Unit/ViewModel/OrderProgressTest.php`.

**Thème `Myca/default`**
- `Magento_Customer/layout/customer_account.xml` (cadre commun), `customer_account_index.xml`, et une mise en page par page pour le titre du bandeau (profil, adresses, favoris, avis, paramètres, détail de commande, moyens de paiement).
- `Magento_Customer/templates/account/{navigation,myca-nav-panel,myca-banner}.phtml`, `account/dashboard/myca-dashboard.phtml`.
- `Magento_Sales/templates/order/{history,myca-orders-table,myca-tracking}.phtml`, `layout/sales_order_history.xml`.
- `Magento_Theme/templates/html/{header-account,footer-account}.phtml`.
- Styles des cartes de l'espace client (`myca/layout.css`), titre de carte (`myca/components.css`), traductions (`i18n/fr_FR.csv`), icônes et image de bandeau Figma.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 24 tests, 44 assertions**, dont la correspondance statut → libellé et le suivi |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal, 8 pages × 4 largeurs | **Aucun** |

Contrôles faits avec un **client de démonstration local**, uniquement dans la base locale :
- Jean Dupont, avec une adresse `example.com` ;
- 5 commandes à des statuts différents ;
- 3 favoris et 1 avis.

L'envoi d'e-mails est désactivé en local (aucun serveur de messagerie).

Captures : `docs/etape-4/` (m28, m29, m29-detail, m31, m32, m33, m34, m37 aux quatre largeurs).

## 4. Écarts avec Figma et le cahier des charges

1. **Rubriques des lots suivants non affichées** : « Mes devis » (`Myca_Quote`), « Mes passeports MYCA » (`Myca_Passport`) et « Mon programme fidélité » (`Myca_Loyalty`). Elles apparaîtront avec leurs modules : aucun lien vers une page inexistante. Pour la même raison, il n'y a pas de carte « points fidélité » au tableau de bord.
2. **Notifications et messagerie** (cloche, enveloppe de l'en-tête) : non affichées. Elles dépendent du module de support (lot 1, à planifier).
3. **Retours / SAV** dans « Mes commandes » : avec `Myca_Rma` (étape 6).
4. **Recherche, période et collection** dans les filtres de M29 : seuls les filtres par statut sont livrés.
5. **Statut « Livrée »** : il faudra qu'un transporteur ou l'équipe logistique fasse passer la commande à ce statut (aujourd'hui manuel, depuis l'administration).
6. **Pages Magento natives** (détail de commande, favoris, avis) : cadre et traductions MYCA, mais pas encore la mise en forme fine des maquettes.
7. **Aide / FAQ** absente du menu : la page n'existe pas encore (M38/M46).

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Statuts clients** : « Client MYCA » par défaut. Faut-il des paliers (ex. « Client Privilège ») et selon quels critères ? |
| V2 | Qui passe une commande à « Livrée » : le transporteur automatiquement ou la logistique à la main ? |
| V3 | Questions des étapes précédentes toujours ouvertes, en particulier la **licence Hyvä Checkout**, les transporteurs et les destinations. |
