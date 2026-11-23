# MYCA — Étape 6 (lot 2) : programme fidélité (`Myca_Loyalty`, M36)

Livré le 23/11/2026. **Point de validation : STOP.** Les autres modules du lot 2 attendent votre accord :
- `Myca_Quote` (devis) ;
- `Myca_Passport` (passeport du vêtement) ;
- `Myca_PartnerSales` (M21, qui dépend de la question V3 de l'étape 5).

## 1. Ce qui est livré

### Règles (cahier des charges « Fidélité : acquisition, retrait et retour »)

**Activation**
- Le programme est **désactivé par défaut**. Il s'active dans Magasins › Configuration › MYCA › Programme fidélité.
- Taux, paliers de récompense et expiration sont **paramétrables**. Aucune valeur de la maquette n'est écrite dans le code.

**Gain**
- Les points sont crédités **en attente** à l'enregistrement de la commande (vitrine, administration ou API), **ligne par ligne**.
- Ils portent sur le montant payé TTC **après remises**, bons fidélité compris, et **hors livraison**.
- L'arrondi se fait à l'entier inférieur, par ligne. Ce chiffre est le même partout.

**Validation**
- Les points deviennent utilisables quand la commande est **payée, livrée et que son délai de retour est écoulé**, sans retour en cours. Le délai est celui de `Myca_Rma` : 14 jours, ou 30 jours si l'option est activée.
- Une tâche planifiée s'en charge toutes les heures.

**Annulation de commande** : les points encore en attente sont annulés.

**Retour et remboursement**
- Chaque avoir retire les points **des seules lignes remboursées, au prorata des quantités**. Cela vaut pour un retour `Myca_Rma` comme pour un remboursement saisi dans l'administration.
- Le calcul porte sur le cumul remboursé de la ligne : plusieurs remboursements partiels ne créent pas d'écart, et l'on ne retire jamais plus que le gain de la ligne.
- Les points en attente sont retirés en premier, puis les points utilisables.
- Si des points déjà dépensés proviennent d'articles retournés, le solde peut devenir **négatif**. Il est expliqué au client et compensé par ses achats suivants (voir V3).

**Bons d'achat**
- « Utiliser maintenant » transforme des points en bon d'achat : un code Magento à **usage unique**, **réservé à son titulaire** (refusé sur un autre compte).
- En cas de **remboursement total** de la commande où le bon a servi, ses points sont **restitués une seule fois**.
- En cas de remboursement partiel, la remise du bon est déjà répartie sur les lignes par Magento : le montant remboursé ne dépasse jamais ce qui a été payé.

**Ventes B2B** : les commandes des boutiques ne rapportent pas de points (option, désactivée par défaut).

**Expiration** (facultative) : les points utilisables expirent après N mois sans gain. N = 0 par défaut, donc pas d'expiration.

**Journal**
- Il fonctionne en **ajout seul** : n° de mouvement, commande, ligne, avoir ou bon, événement, variation, soldes avant et après, motif, auteur, date.
- Chaque mouvement a une **clé d'unicité** : un événement rejoué (commande, avoir, validation, double envoi d'un formulaire) ne compte jamais deux fois.
- Le solde du client est **verrouillé** pendant chaque mouvement.

### Écrans

**M36 « Mon programme fidélité »** (`/fidelite`, menu de l'espace client quand le programme est actif) :
- points utilisables et en attente ;
- prochaine récompense ;
- progression par paliers ;
- comment gagner des points (règles actives seulement) ;
- récompenses, avec « Utiliser maintenant » ou « Encore N points » ;
- bons à utiliser ;
- historique, avec « Voir tout » ;
- **détail du solde : disponibles, en attente, utilisés, annulés, expirés**.

**Administration** (Clients › Programme fidélité) :
- journal complet en grille native, avec filtres ;
- « Ajuster un solde » : e-mail, points et motif. C'est un droit séparé, et le formulaire est protégé contre le double envoi.

## 2. Fichiers

**`app/code/Myca/Loyalty/`**
- `Model/PointsCalculator` : calculs purs (gain, prorata, répartition, paliers).
- `Model/Ledger` : journal et soldes, avec verrou et clé d'unicité.
- `Model/LoyaltyService` : règles métier.
- `Model/Config` et `Config/Backend/Rewards` : format des paliers vérifié à l'enregistrement.
- `Observer/` : commande enregistrée, commande annulée, avoir.
- `Cron/` : validation et expiration.
- `Plugin/RestrictVoucherToOwner`.
- `Controller/` : page, utilisation d'un bon, grille et ajustement dans l'administration.
- `ViewModel/Program`, `etc/` (schéma, ACL, menu, configuration, tâches planifiées), `view/adminhtml/`, tests.

**Thème**
- `Myca_Loyalty/` : lien de menu, mise en page, gabarit M36.
- Icône du menu dans `Magento_Customer/templates/account/navigation.phtml`.

**Commandes** : `bin/magento module:enable Myca_Loyalty`, puis `setup:upgrade`, `setup:di:compile`, le build Tailwind et `cache:flush`. Le cron Magento doit tourner pour la validation des points.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK : 61 tests, 4 701 assertions**. Dont les exemples 1 et 3 du cahier, 1 article sur 3, remboursement rejoué, et un **test de propriété** sur 500 suites de remboursements partiels aléatoires (jamais plus que le gain, exactement le gain au retour total) |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal, M36 × 4 largeurs | **Aucun** |

Contrôles avec les vrais services Magento (scripts locaux `var/tmp/myca-demo/loyalty*.php`, hors dépôt). Le programme a été activé **en local seulement**, à 1 point par euro, avec des paliers de démonstration.

| Scénario | Résultat |
|---|---|
| Gains des commandes du client de démo, puis rejoués | 305 points en attente, **inchangés au rejeu** |
| Commande d'une boutique | Aucun point |
| Commande annulée | Ses 79 points en attente sont annulés |
| Validation, puis rejouée | Seule la commande livrée il y a 16 jours est validée (108 points) ; 0 au rejeu |
| Bon de 6 € contre 100 points ; bon de 200 points | Créé ; le second est **refusé** (solde insuffisant) |
| Bon dans le panier du titulaire, puis d'un autre compte | Appliqué ; **« code non valide »** pour l'autre compte |
| Retour d'un article dont les points sont dépensés | −79 points : solde **−71** (exemple 2 du cahier) |
| Même avoir rejoué | Aucun changement |
| Remboursement total d'une commande en attente | Ses points en attente sont retirés |
| Bon utilisé, puis commande remboursée en totalité | Bon « restitué », +100 points, **une seule fois** |
| Invariant « somme des mouvements = solde » ; clés en double | **OK** ; 0 |
| Ajustement dans l'administration envoyé deux fois | **Un seul** mouvement |

Captures : `docs/etape-6-fidelite/` (M36 aux 4 largeurs).

## 4. Écarts avec Figma et le cahier des charges

1. **Parrainage, avis produit, anniversaire** (maquette) : non activés. Le cahier demande de les activer « seulement après définition ».
2. **« Mes avantages Client Privilège » et « Devenez Client Privilège »** : non affichés, les statuts clients ne sont pas définis (question V1 de l'étape 4).
3. **Notification au client** lors d'un ajustement ou d'un retrait : pas d'e-mail (pas de serveur de messagerie local, modèles à fournir). Le mouvement et son motif sont visibles dans l'historique.
4. **Carte « points » du tableau de bord client (M28)** : pas encore ajoutée. Elle peut l'être maintenant que le module existe.
5. **Utilisation d'un bon dans le tunnel de commande** : vérifiée dans le panier. Le tunnel reste bloqué par la licence Hyvä Checkout.
6. **Paiements mixtes et frais de livraison remboursés** : les points ne portent jamais sur la livraison, donc un remboursement de port n'y touche pas. Les paiements mixtes ne sont pas encore possibles (aucun prestataire).

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Barème** : nombre de points par euro et paliers de récompense (la maquette montre 1 point/€ et 100 points = 6 €, non contractuels). |
| V2 | **Validation des points** : à la fin du délai de retour après livraison (livré), ou un autre déclencheur ? |
| V3 | **Points dépensés puis article retourné** : solde négatif compensé par les achats suivants (livré), ou retenue sur le remboursement ? Validation juridique attendue. |
| V4 | **Expiration** : durée de validité, et faut-il prévenir le client avant ? |
| V5 | **Points sur les ventes B2B** : toujours non ? |
| V6 | Règlement du programme à intégrer aux CGV ; parrainage, avis et anniversaire à définir. |
| V7 | Questions des étapes précédentes, en particulier la **licence Hyvä Checkout**. |
