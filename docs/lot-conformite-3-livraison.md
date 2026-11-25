# MYCA — Lot de conformité 3 : paiement par carte avec Stripe

Livré le 25/11/2026, en **mode test**. Point 3 du plan de l'audit.

## 1. Ce qui est en place

- **Module officiel Stripe Payments 4.8**, acquis gratuitement sur la Marketplace Adobe (compte ict consulting) et installé par Composer dans Docker.
  - Seuls 4 paquets Stripe ont été ajoutés ; aucun paquet existant n'a été modifié.
  - Stripe Tax est installé mais **désactivé** : la TVA reste calculée par Magento.
- **Réglages MYCA**, posés par le patch `Myca_Core::ConfigureStripe` :
  - libellé « Carte bancaire », logos CB, Visa et Mastercard ;
  - formulaire de carte intégré au tunnel (Stripe Payment Element) ;
  - débit à la commande ;
  - mode test par défaut.
- **Clés de test** saisies dans la configuration ; la clé secrète est chiffrée par Magento.
- **Aucune donnée de carte chez MYCA** : la carte est saisie dans le formulaire hébergé par Stripe.
- **Paiement par carte réservé aux particuliers.** Les boutiques gardent le virement : la carte peut leur être ajoutée dans *MYCA › Commandes partenaires › Paiement*.

## 2. Webhooks

- **Signature** : chaque webhook est vérifié par le module avec le secret de signature. Une signature falsifiée est refusée (HTTP 400).
- **En local** : Stripe CLI tourne dans le conteneur `myca-stripe-cli`, qui retransmet les événements à `http://nginx/stripe/webhooks`. Son secret est enregistré dans la table `stripe_webhooks`.
  - Pour l'arrêter : `docker rm -f myca-stripe-cli`.
- **En production** : depuis l'admin, le module crée lui-même le webhook chez Stripe vers l'adresse publique du site.

## 3. Tests réalisés

| Test | Résultat |
|---|---|
| Paiement par carte de test 4242 dans le vrai tunnel (navigateur) | ✅ Commande 000000036 payée, facturée ; 4 webhooks acceptés ; points fidélité crédités « en attente » |
| Paiement par l'API (carte `pm_card_visa`) | ✅ Commande 000000033 payée ; une facture, une capture, une réservation de stock |
| **R02** : webhook `charge.succeeded` renvoyé 2 fois avec une signature valide | ✅ « Already processed » ; ni seconde facture, ni second paiement, ni mouvement de stock |
| Signature de webhook falsifiée | ✅ Refusée (400) |
| Carte refusée (`pm_card_chargeDeclined`) | ✅ Message « Your card was declined » ; **aucune commande créée** |
| Carte exigeant 3-D Secure | ✅ Authentification demandée ; aucune commande avant validation par le client |
| Remboursement **partiel** en ligne (frais de port, 7,90 €) puis **total** (89,90 €) depuis un avoir | ✅ Remboursements reçus par Stripe (`charge.refunded`) ; commande clôturée ; transaction de remboursement enregistrée |

**Correction pendant le lot** : le tunnel de commande (thème de repli Luma) **plantait depuis le lot 1**. Le bandeau cookies, conçu pour Hyvä, y était chargé sans ses dépendances. Il est désormais retiré du tunnel, et le gabarit est protégé.

## 4. À faire avant la mise en ligne

1. **Moyens de paiement Stripe** : le compte propose aussi Bancontact, MB WAY, Amazon Pay, Satispay et EPS. Le cahier n'autorise que la carte avant validation contractuelle. Il faut les désactiver dans le tableau de bord Stripe (*Paramètres › Moyens de paiement*).
2. **« Chèque / mandat »** (moyen de test Magento) : à désactiver (*Ventes › Moyens de paiement*).
3. **Mode production de Magento** : en mode développeur, les erreurs de l'API affichent la trace technique complète.
4. **Clés live et webhook** :
   - saisir les clés live dans l'admin ;
   - passer le mode Stripe à « Live » ;
   - laisser le module créer le webhook vers l'adresse publique.
5. **Clé secrète de test** : la régénérer chez Stripe, car elle a été partagée dans la conversation, puis la ressaisir.
6. **Libellés du récapitulatif** en anglais (« Order Total Incl. Tax ») : à traduire avec le point multilingue.
