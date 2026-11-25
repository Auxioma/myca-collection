# MYCA — Lot de conformité 2 : commande partenaire M17 (`Myca_Dropship`)

Livré le 25/11/2026. Point 2 du plan de l'audit : une boutique partenaire peut désormais passer commande, en **dropshipping** ou en **achat de stock**, au tarif partenaire.

## 1. Écran M17 « Passer une commande » (`/partenaire/commander`)

L'écran reprend la maquette : 4 étapes, avec un récapitulatif recalculé par le serveur à chaque action.

1. **Sélection des produits**
   - Mode de commande : seuls les modes autorisés à la boutique sont proposés.
   - Filtres collection, catégorie, disponibilité, et recherche.
   - Cartes modèles : prix public conseillé HT, tarif partenaire indicatif, couleurs, pièces disponibles.
2. **Quantités par taille**
   - Choix de la couleur, puis une quantité par taille, avec le stock vendable affiché.
   - Un modèle sans taille (casquette) est en « taille unique ».
3. **Livraison**
   - **Dropshipping** : destinataire final (nom, adresse, pays desservi, téléphone), limité aux données nécessaires à la livraison. Prix de vente HT facultatif, qui alimente « Mes marges & ventes ».
   - **Achat de stock** : adresse de la boutique, issue de son profil.
4. **Validation et paiement**
   - Moyens de paiement réservés aux boutiques.
   - Acceptation des conditions de vente professionnelles, dont la date est tracée sur la commande.
   - Une **seconde validation ne crée jamais une seconde commande** : le bouton est désactivé, le panier désactivé à la création, et Magento verrouille la création de commande.

**Récapitulatif** : prix public HT, remise partenaire, total produits HT, livraison professionnelle, TVA (« régime à valider »), total TTC. Sur mobile, une barre fixe affiche le total et mène au récapitulatif.

**Garde-fous** :
- une boutique qui ouvre le panier ou le tunnel B2C est renvoyée vers M17 ;
- un particulier ne voit jamais les tarifs, le virement ni la livraison professionnelle.

**Accès** : le menu de l'espace partenaire et le bouton rouge du tableau de bord mènent à M17.

## 2. Tarifs partenaires (calculés côté serveur)

- **Dropshipping** : remise unitaire sur le prix public conseillé.
- **Achat de stock** : paliers selon la quantité du modèle dans la commande (par exemple dès 50 pièces −5 %, dès 100 pièces −10 %).
- **Remise contractuelle** par boutique (dropshipping et/ou stock), saisie sur la fiche de la boutique (*Clients › Partenaires B2B*). Elle remplace la grille standard du mode.
- **Minimum** par modèle et par couleur en achat de stock. La validation est bloquée tant qu'il n'est pas atteint.
- Les prix sont recalculés à chaque calcul du panier. Une boutique ne peut donc jamais obtenir un tarif qui n'est pas le sien.

**Réglages** : *Magasins › Configuration › MYCA › Commandes partenaires*.
- **Valeurs par défaut neutres** (aucune remise, aucun minimum, port à 0 €) tant que la grille n'est pas validée.
- **Valeurs du test local**, retirées depuis : 10 % en dropshipping, 5 % dès 50 pièces, 10 % dès 100 pièces, minimum de 10, port à 6,90 € et 15 €.

## 3. Livraison et paiement professionnels

- **« MYCA – Livraison professionnelle »** : transporteur distinct du B2C, proposé aux seules boutiques et uniquement vers les destinations desservies à domicile.
  - Dropshipping : frais par commande.
  - Achat de stock : frais par commande, offerts au-delà d'un montant de produits.
- **Virement bancaire** : activé et réservé aux boutiques. La commande est préparée à réception du virement. Les instructions contiennent `[À COMPLÉTER : IBAN et BIC]`.
- **Paiement par carte** : un prestataire qui fournit une page de paiement sera redirigé automatiquement après la validation. Il reste à brancher (point 3 de l'audit).

## 4. Administration

- **Fiche commande** : encadré « Commande partenaire » avec le mode, la boutique (lien vers sa fiche) et, en dropshipping, la mention « vendeuse au client final ».
- **Bon de livraison sans prix** (dropshipping) : la boutique y figure comme **vendeur**, le client final comme destinataire. Il ne contient **aucun prix** ; MYCA n'apparaît qu'en expéditeur. Il est réservé aux administrateurs ayant le droit « expéditions ».
- **E-mails de la commande** : ils vont à la boutique, jamais au client final.

## 5. Vérifications

- **Tests unitaires** : 95 réussis, dont les tarifs (paliers dans le désordre, contrat prioritaire, bornes) et les règles (minimum, destinataire normalisé, champs superflus écartés).
- **PHPStan niveau 8** : 0 erreur.
- **PHPCS** : 0 erreur.
- **Parcours testés dans le navigateur** :
  - ajout de 2 S et 3 M, avec des lignes distinctes ;
  - remise de 10 % en dropshipping ;
  - passage en achat de stock : minimum signalé sous 10 pièces, −5 % dès 50 pièces ;
  - retrait d'une ligne ;
  - refus d'une quantité supérieure au stock ;
  - code postal invalide refusé.
- **Commandes passées en local** :
  - **n° 31**, achat de stock : livrée à la boutique, port offert au-delà de 500 €, virement ;
  - **n° 32**, dropshipping : livrée à Léa Martin, facturée à la boutique, port de 6,90 €, prix de vente déclaré 149,90 €.
- **Garde-fous** : particulier sans tarif partenaire, sans virement ni livraison professionnelle ; boutique redirigée du panier et du tunnel B2C vers M17.
- **Remise contractuelle** : 150 % refusé, 15 % appliqué dans M17.
- **Affichage** : aucun débordement à 375, 768, 1280 et 1600 px.
- **Captures** : `docs/lot-conformite-2/`.

## 6. Points à valider

1. **Grille tarifaire** : remise dropshipping, paliers de l'achat de stock, minimum par couleur, frais de port B2B. Tout est neutre par défaut.
2. **IBAN et BIC de MYCA** pour les instructions de virement. Faut-il aussi d'autres moyens (carte par le prestataire, paiement à 30 jours) ?
3. **TVA** :
   - les boutiques d'un autre pays de l'UE avec un n° de TVA valide relèvent normalement de l'autoliquidation, non automatisée en attendant le comptable ;
   - **les frais de port, B2C compris, n'ont aujourd'hui aucune classe de TVA** : ils sont facturés sans TVA. À corriger avec le comptable.
4. **Annulation par la boutique** : jusqu'à quel stade (avant paiement, avant préparation) ?
5. **Boutique de démonstration n° 5** : son SIRET de démo est invalide, ce qui empêche d'enregistrer sa fiche. C'est sans incidence en production.
