# MYCA — Étape 3 (partielle) : panier et `Myca_Shipping`

Livré le 22/11/2026. **Point de validation : STOP.**
Le tunnel de commande (P07 à P10) **n'est pas livré** : il dépend de Hyvä Checkout, module payant dont la licence n'est pas disponible (voir §6).

## 1. Ce qui est livré

| Élément | Rendu |
|---|---|
| P06 Panier | En-tête épuré du tunnel (logo, paiement sécurisé, aide), suivi des 5 étapes, carte « Mon panier » (quantité −/+, prix unitaire, suppression, alerte « Plus que N en stock » via MSI), « Vous aimerez aussi », récapitulatif (sous-total, remise, livraison, TVA, total TTC), barre « Relais offert », « Passer la commande », code promo, réassurance |
| Mini-panier | Fenêtre compacte : articles, sous-total, message « point relais offert » ou « Plus que X € », « Continuer mes achats », « Voir mon panier » |
| `Myca_Shipping` | Point relais offert dès 100 € TTC **après remise**, sur les produits éligibles, hors frais de livraison ; destinations desservies ; livraison à domicile MYCA ; commande refusée vers un pays non desservi |
| TVA | Prix catalogue TTC, TVA française 20 %, remise appliquée sur le TTC |

**Mobile** : le bouton « Passer la commande » reste visible en bas d'écran ; le récapitulatif passe sous les articles ; la suppression d'une ligne se place en haut à droite ; zones tactiles de 44 px.

## 2. Fichiers créés ou modifiés

**`app/code/Myca/Shipping/`** (nouveau module)
- `Model/Config.php` : seuil, transporteurs « point relais », destinations, barème domicile.
- `Model/FreeRelayThreshold.php` : montant éligible (TTC, remises déduites, lignes de variantes ignorées), seuil atteint, montant restant.
- `Plugin/ApplyDestinationRules.php` : relais retiré si le pays n'est pas desservi en relais, offert si le seuil est atteint ; domicile retiré si le pays n'est pas desservi à domicile.
- `Plugin/BlockUnservedDestination.php` : refuse la validation d'une commande vers un pays non desservi.
- `Plugin/AddFreeRelayToCartSection.php` : progression du seuil dans les données du mini-panier.
- `Model/Carrier/Home.php` : transporteur « MYCA – Livraison à domicile » (prix par pays et tranche de poids).
- `ViewModel/FreeRelayProgress.php`, `Setup/Patch/Data/AddRelayEligibleAttribute.php` (attribut « Éligible au point relais offert », oui par défaut, chargé avec les lignes de panier).
- Administration : *Magasins > Configuration > MYCA > Livraison* (seuil, transporteurs relais, tableau des destinations) et *Ventes > Modes de livraison > MYCA – Livraison à domicile* (barème). Droit ACL `Myca_Shipping::config`.
- `Test/Unit/Model/FreeRelayThresholdTest.php`.

**`app/code/Myca/Core/`** : `Setup/Patch/Data/ConfigureFrenchVat.php` (affichage TTC, origine France, taux et règle TVA 20 %).
**`app/code/Myca/Catalog/`** : `ViewModel/StockLevel.php` (quantité vendable MSI, pour l'alerte de stock faible).

**Thème `Myca/default`**
- `Magento_Checkout/layout/checkout_cart_index.xml` ; gabarits `php-cart/{wrapper,form,coupon,onepage-link,noItems,myca-summary-title,myca-relay-progress,myca-reassurance}.phtml`, `php-cart/item/default.phtml`, `php-cart/item/renderer/actions/remove.phtml`, `myca/steps.phtml`.
- `Magento_Tax/templates/php-cart/totals/*.phtml` (lignes du récapitulatif).
- `Magento_Theme/templates/html/header-checkout.phtml`, `html/cart/cart-drawer.phtml`.
- Icônes Figma : `headset`, `card`, `check-circle`, `file`, `tag`.

## 3. Écart d'architecture

L'audit prévoyait deux tables (`myca_shipping_destination`, `myca_shipping_rate`). Les destinations et le barème sont finalement des **tableaux de configuration** (une ligne par pays ou par tranche de poids, modifiables par site web, protégés par l'ACL). C'est plus simple pour ces volumes et sans écran d'administration à développer. Le barème B2B viendra avec `Myca_Partner`.

## 4. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 16 tests, 25 assertions**, dont le seuil : 99,99 € non atteint, 100,00 € et 100,01 € atteints, remise qui fait repasser sous 100 €, produit non éligible exclu, variantes non comptées deux fois |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Panier réel, France, 79,90 € | Relais 4,90 €, domicile 7,90 €, TVA incluse 13,32 € |
| Panier réel, France, 159,80 € | **Relais offert (0 €)**, domicile 7,90 € |
| Panier réel, Allemagne (non desservie) | Aucun tarif ; validation de commande **refusée** avec un message clair |
| Débordement horizontal (panier, 4 largeurs) | Aucun |

Les tarifs « relais » des contrôles utilisent le transporteur forfaitaire de Magento configuré **localement** comme transporteur relais, en attendant le module officiel (Q6).

Captures : `docs/etape-3/p06-*.png`, `docs/etape-3/minipanier-*.png`.

## 5. Écarts avec Figma et le cahier des charges

1. **Mini-panier** : titre « Mon panier » au lieu de « Article ajouté au panier ». La fenêtre sert aussi quand on clique sur l'icône panier ; elle liste tous les articles.
2. **Estimation de livraison par pays / code postal** : retirée du panier (absente de la maquette). La ligne « Livraison » du récapitulatif apparaît dès qu'un mode est choisi ; avant, la barre « Relais offert » indique si le relais sera offert.
3. **« Service client (horaires à confirmer) »** : horaires **À FOURNIR**, non affichés.
4. **Blocage du paiement si l'API transporteur est indisponible** : non livré, il dépend du transporteur retenu (Q6).
5. **TVA des autres pays de l'UE** : seule la France est configurée. Ouvrir une destination européenne demandera les taux du pays (régime OSS) : à voir avec le comptable.
6. **Bannière « Rupture concurrente »** de la maquette : c'est une note de comportement. Magento affiche déjà un message sur la ligne concernée quand le stock manque au passage en caisse.

## 6. Points à valider

| # | Question |
|---|---|
| V1 | **Licence Hyvä Checkout** (payante) : sans elle, P07–P10 ne peuvent pas être construits. Autre option gratuite : le checkout Luma en mode compatibilité (module Hyvä de repli), moins fidèle à la maquette et chargé en JavaScript Luma. |
| V2 | **Transporteurs (Q6)** : Mondial Relay ? Colissimo ? Leurs modules officiels pour Magento, et leurs codes, à renseigner dans *MYCA > Livraison > Transporteurs « point relais »*. |
| V3 | **Destinations (Q8)** : seule la France est active par défaut. Quels pays, en relais et à domicile ? Quel barème domicile (le test local utilise 7,90 €) ? |
| V4 | **Points fidélité (Q7)** : doivent-ils réduire le montant éligible au seuil comme une remise ? |
| V5 | Taux et classes de TVA à faire valider par le comptable. |
| V6 | Questions des étapes 1 et 2 toujours ouvertes (groupes partenaires, liens sociaux, pack de langue, filtres, tri « Popularité »). |
