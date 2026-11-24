# MYCA — Tunnel de commande : repli Luma (24/11/2026)

## Problème signalé

« Passer la commande » menait à une page presque vide : aucun module de tunnel de commande n'était installé. Le thème Hyvä affichait seulement « No Checkout module installed » (point V1 de l'étape 3, resté sans réponse).

Cette page d'attente avait en plus été **mise en cache** par le cache de pages Magento : même après installation d'un tunnel, `/checkout` restait servi depuis le cache.

## Solution retenue : repli Luma (gratuit)

- **Paquets officiels Hyvä**, ajoutés par leurs dépôts GitHub comme les autres paquets Hyvä du projet. Ils ont été inspectés avant installation : licence OSL-3.0, aucun script Composer, aucun appel système ou réseau.
  - `hyva-themes/magento2-theme-fallback` 1.0.4 ;
  - `hyva-themes/magento2-luma-checkout` 1.1.7.
- **Bascule** : `/checkout` passe sur le thème **`Myca/checkout`**, enfant de Luma, créé pour l'occasion. Le reste du site reste sur `Myca/default` (Hyvä).
  - Réglage verrouillé dans `app/etc/config.php` : `hyva_theme_fallback/general/theme_full_path = frontend/Myca/checkout`.
- **Habillage MYCA du thème `Myca/checkout`** :
  - en-tête marine avec le logo MYCA, étapes et boutons rouges ;
  - police Inter ;
  - pied de page MYCA à la place du copyright Magento ;
  - textes du tunnel et de la page de confirmation traduits en français.

## Contrôles

| Scénario | Résultat |
|---|---|
| Client connecté : panier → « Passer la commande » | Tunnel MYCA : adresse, modes de livraison `Myca_Shipping`, paiement |
| Commande complète depuis le navigateur | Commandes **000000027** et **000000028** créées ; page « Merci pour votre commande ! » |
| Invité avec un panier | Tunnel affiché |
| Cache de pages | `/checkout` n'est plus mis en cache (`MISS` à chaque appel) |
| Débordement horizontal, 4 largeurs | Aucun |

## À faire lors d'une mise en production

- Après le déploiement : `bin/magento setup:upgrade`, `setup:static-content:deploy fr_FR` (thèmes `Myca/default` **et** `Myca/checkout`), `cache:flush`, **puis vider `var/page_cache`**. L'ancienne page d'attente, mise en cache, ne disparaît pas avec un simple nettoyage par étiquette.

## Limites

- Ce tunnel est celui de Magento, **moins fidèle aux maquettes P07–P10** et plus lourd en JavaScript que Hyvä Checkout. Il pourra être remplacé plus tard sans toucher au reste du site.
- **Paiement** : seul le moyen de test « Chèque ou virement » est actif en local. Le prestataire de paiement (Mollie est présent dans les dépendances) reste à configurer avec vos identifiants.
- **Libellés de livraison** : « Fixed / Point relais (test local) » est le transporteur de test local. Les vrais transporteurs restent à brancher (question de l'étape 3).
