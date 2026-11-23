# MYCA — Étape 5 : espace partenaire B2B (M16–M27)

Livré le 23/11/2026. **Point de validation : STOP.** L'étape 6 (`Myca_Rma`, puis modules du lot 2) attend votre accord.
Rappel : le tunnel de commande (P07–P10) reste bloqué par la licence Hyvä Checkout (étape 3). Cela bloque aussi l'écran M17.

## 1. Ce qui est livré

### Deux nouveaux modules

**`Myca_Partner`** : partenaires, comptes et cloisonnement.
- **Tables** :
  - `myca_partner` : boutique, n° partenaire, SIRET/TVA, statut, modes de vente, coordonnées, préférences ;
  - `myca_partner_user` : comptes clients de la boutique, avec un rôle « titulaire » ou « collaborateur » ;
  - `myca_partner_application` : candidatures P17 ;
  - `myca_partner_order` : rattachement d'une commande à une boutique.
- **Groupe client « Partenaire MYCA »** : créé automatiquement. Il donne l'identité partenaire (accent rouge, fond bleu nuit) et pourra porter les tarifs partenaires.
- **Rattachement des commandes** : toute commande d'un compte partenaire est rattachée à sa boutique, qu'elle vienne de la vitrine, de l'administration ou de l'API. Le rattachement est figé à la création de la commande.
- **Cloisonnement** :
  - toutes les lectures (commandes, factures, expéditions, top produits, équipe, clients) sont filtrées sur la boutique connectée ;
  - sur les pages Magento de commande et de facture, un utilisateur voit les commandes de ses collègues, jamais celles d'une autre boutique.
- **Accès** : réservé à un partenaire **actif**.
  - Un visiteur est renvoyé vers la connexion.
  - Un client B2C, ou un partenaire en attente ou suspendu, voit un message.
  - À la connexion, un partenaire arrive directement sur son tableau de bord.
- **Configuration** (Magasins › Configuration › MYCA › Espace partenaire) : horaires, téléphone et e-mail du Service Partenaire B2B, affichés dans le pied de page. Valeurs de la maquette par défaut, « à confirmer ».

**`Myca_Dropship`** : mode de vente de chaque commande.
- **Table `myca_dropship_order`** :
  - mode « dropshipping » ou « achat de stock » ;
  - livraison au client final ;
  - bon de livraison sans prix ;
  - **prix de vente HT déclaré** par la boutique.
- **Marge estimée** = prix de vente déclaré − achat MYCA HT (port compris). Elle n'est calculée que sur les commandes dont le prix de vente est déclaré : aucun chiffre n'est inventé. Ce n'est pas la marge de MYCA (mention affichée).

### Écrans (identité partenaire : en-tête, menu, bandeau et pied de page Figma)

| Écran | Page | Contenu |
|---|---|---|
| M16 | Tableau de bord `/partenaire` | Commandes en cours, expéditions, ventes et marge du mois (avec évolution) ; accès rapides ; 3 dernières commandes ; fonctionnement en 4 étapes ; performances sur 30 jours ; top 3 produits ; nouvelles collections ; 2 modèles B2B ; avantages |
| M19 | Mes commandes | Indicateurs, filtres par statut, recherche (n°, référence, modèle, destinataire), période, tableau (mode, quantité, montant HT, transporteur et n° de suivi), détail (articles, HT, TVA, TTC, prix déclaré et marge), suivi, actions (facture, bon de commande, support) |
| M20 | Suivi des livraisons | Indicateurs (en cours, livrées, en préparation, **délai moyen d'expédition mesuré**), filtres, recherche, expéditions (n° de suivi, transporteur, destination, date), détail, étapes réelles, actions |
| M22 | Mes factures | Total facturé HT, réglé, en attente, nombre ; filtres réglées / en attente ; détail (lignes, totaux) ; impression |
| M24 | Mes clients | Clients finaux livrés en dropshipping : total, fidèles (2 commandes ou plus), actifs (90 jours), panier moyen déclaré ; segments ; fiche, historique, statistiques |
| M25 | Mon profil boutique | Informations, logo, coordonnées, présentation (500 caractères), date d'ouverture, annuaire, 4 préférences, taux de complétion. **Modifiable par le titulaire seulement** ; n° partenaire, statut, SIRET, TVA et modes de vente restent gérés par MYCA |
| M26 | Paramètres | Paramètres généraux, sécurité (mot de passe, connexion), **6 notifications par utilisateur** (enregistrées), équipe de la boutique |
| M27 | Tableau de bord (2) | Autre version du même tableau de bord (carte « Support partenaire » avec photo, raccourci « Livraison rapide », sans modèles B2B ni avantages). **M16 a été retenu** ; voir V8 |

**Correction de l'étape 4** : les espaces client et partenaire étaient limités à 1 232 px au lieu de toute la largeur de la maquette, et le tableau « Mes commandes » du client était coupé. Ils occupent désormais toute la largeur.

## 2. Fichiers

- `app/code/Myca/Partner/` :
  - `Api/` : `PartnerInterface`, `PartnerRepositoryInterface` ;
  - `Model/` : partenaire, dépôt, partenaire courant, lecture cloisonnée, préférences ;
  - `Controller/` : 7 pages et 2 enregistrements ;
  - `Plugin/` : cloisonnement des pages commande, redirection du compte ;
  - `Observer/` : rattachement des commandes ;
  - patch du groupe client, `etc/` (schéma, routes, configuration, ACL), `ViewModel/PartnerSpace`, tests.
- `app/code/Myca/Dropship/` : schéma, `Model/SalesSummary`, `Model/ResourceModel/DropshipOrder`, `ViewModel/PartnerSales`, tests.
- **Thème `Myca/default`** :
  - `Myca_Partner/layout/` : cadre commun `myca_partner_space` et 7 pages ;
  - `Myca_Partner/templates/` : en-tête, menu, bandeau, pied de page, 7 écrans, cartes KPI, interrupteur ;
  - 17 icônes Figma et 4 visuels (bandeau B2B, traits, casquettes) ;
  - `myca/layout.css` : pleine largeur des espaces.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 36 tests, 71 assertions**. Nouveaux tests : cloisonnement (6 cas), statuts et modes du partenaire, calcul de la marge et des évolutions |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal : 7 écrans partenaire et 2 écrans client × 4 largeurs | **Aucun** |

Contrôles dans le navigateur, avec des données de démonstration locales (`var/tmp/myca-demo/partner.php`, hors dépôt) :
- la boutique « Mode Élégance » (titulaire et vendeuse, 8 commandes) ;
- une **boutique concurrente** avec sa propre commande.

| Scénario | Résultat |
|---|---|
| Commande passée par la vendeuse, ouverte par la titulaire | Visible (commande et facture) |
| Commande de la boutique concurrente (page Magento, liste, clients) | **Refusée / absente** |
| Visiteur non connecté | Renvoyé vers la connexion |
| Client B2C sur `/partenaire` | Refusé, avec le message « réservé aux partenaires » |
| Envoi du formulaire sans clé de sécurité | Refusé |
| Vendeuse qui force l'envoi du profil boutique | **Refusé côté serveur** (aucune modification) |
| E-mail invalide dans le profil | Refusé ; les autres champs ne sont pas modifiés |
| Préférences de notification | Enregistrées par utilisateur |

Captures : `docs/etape-5/` (m16 à m26 aux 4 largeurs, et l'espace client en pleine largeur).

## 4. Écarts avec Figma et le cahier des charges

1. **M17 « Passer une commande »** : non livré. Il dépend du tunnel de commande (licence Hyvä Checkout) et des tarifs partenaires (V2). En attendant, le bouton rouge du tableau de bord mène au catalogue. Rubrique absente du menu.
2. **M21 « Mes marges & ventes »** : non livré. Il faut d'abord valider comment la boutique déclare son prix de vente (V3). Les chiffres existent déjà au tableau de bord.
3. **M23 « Retours & SAV »** : avec `Myca_Rma` (étape 6).
4. **Statut « En fabrication »**, **carte et pourcentage d'acheminement**, **suivi détaillé du transporteur** : aucune donnée ne les alimente. Ils ne sont pas affichés plutôt qu'inventés. Il faudra l'API des transporteurs (question des transporteurs, étape 3).
5. **Notifications et messagerie** (cloche, enveloppe), **avoirs et échéances** de facturation, **règlement en ligne des factures** : non affichés (modules à planifier).
6. **Clients finaux** : pas de consentement marketing, de points fidélité ni de date d'anniversaire (pas de données, et la loyauté relève du lot 2).
7. **Profil** : le logo et la bannière ne sont pas modifiables depuis l'espace (envoi au Service Partenaire). L'**IBAN n'est pas stocké** : données bancaires à cadrer (V5).
8. **Paramètres** : 2FA, sessions, historique des connexions, vente & catalogue, livraison, paiement et intégrations (Shopify, PrestaShop, flux) ne sont pas livrés. L'ajout d'utilisateurs passe par MYCA.
9. **Collection** dans les tableaux et filtres : remplacée par le **mode de vente**, car l'attribut collection ne figure pas sur les variantes de démonstration.
10. **Bon de livraison sans prix** (dropshipping) : le réglage est stocké, mais le document PDF partenaire viendra avec M17.
11. **Administration des partenaires** (validation des candidatures P17, activation, suspension) : les tables existent ; l'écran d'administration et le formulaire P17 restent à faire (V1).
12. **Maquette** :
    - sur la carte rouge de M16, le texte gris est illisible : il est passé en blanc ;
    - les libellés des 4 étapes se chevauchent dans Figma : ils passent à la ligne.

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Admission des partenaires** : qui valide une candidature P17 et selon quels critères ? Faut-il un écran d'administration dès maintenant ? |
| V2 | **Tarifs partenaires** : remise globale (la maquette montre −10 %), grille par volume ou prix par produit ? Minimum de commande (« 100 pièces par couleur, à valider ») ? |
| V3 | **Prix de vente déclaré** : la boutique le saisit-elle à chaque commande dropshipping (M17) ou applique-t-on le prix public conseillé par défaut ? |
| V4 | **Rôles d'équipe** : « titulaire » et « collaborateur » suffisent-ils, ou faut-il les rôles de la maquette (administrateur, gestionnaire, vendeur) avec des droits différents ? |
| V5 | **IBAN et règlement des factures** : prélèvement, virement ou paiement en ligne ? Qui gère les données bancaires ? |
| V6 | Coordonnées du Service Partenaire (horaires, téléphone, e-mail) et pages « CGV Partenaire » à fournir. |
| V8 | Tableau de bord : garder la version M16 (livrée) ou passer à la version M27 ? |
| V7 | Questions des étapes précédentes toujours ouvertes, en particulier la **licence Hyvä Checkout**, les transporteurs et les destinations. |
