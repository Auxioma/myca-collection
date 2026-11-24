# MYCA — Étape 6 (lot 2) : devis (`Myca_Quote`, M30)

Livré le 24/11/2026. **Point de validation : STOP.** Restent du lot 2 :
- `Myca_Passport` (passeport du vêtement, QR) ;
- `Myca_PartnerSales` (M21, qui attend la question V3 de l'étape 5).

## 1. Ce qui est livré

### Parcours (cahier des charges « 3.3 Mes devis »)

1. **Demande depuis le panier.** Dans le panier, l'encart « Commande en volume ou personnalisée ? Demandez un devis » reprend les articles, leurs options et leurs quantités, avec un message libre (personnalisation, délai). Le client **envoie** la demande ou l'**enregistre en brouillon**. Son panier n'est pas vidé.
2. **Proposition de MYCA** (administration › Ventes › Opérations › Devis) :
   - le service client saisit un prix unitaire TTC par ligne (le prix catalogue est affiché à côté), une date de validité et des conditions ;
   - l'envoi crée une **version figée du PDF** (`var/myca_quote/DEV-AAAA-NNNN-vN.pdf`), avec son **empreinte sha256** en base ;
   - une nouvelle proposition crée une nouvelle version, la précédente reste intacte ;
   - un PDF modifié sur le disque est **refusé** au téléchargement.
3. **Réponse du client** (M30) : il accepte ou refuse la proposition, ou annule sa demande. **Accepter ne crée ni commande ni facture.**
4. **Validité.** Le devis est valable jusqu'à sa date de validité incluse, au jour calendaire de la boutique. Une proposition ou un devis accepté qui a dépassé sa validité expire :
   - au moment où le client agit ;
   - ou par une tâche planifiée quotidienne.
5. **« Transformer en commande ».** Les articles du devis remplacent le contenu du panier, aux prix convenus. **Seules les lignes identiques au devis (même article, même quantité) gardent le prix convenu.** Une quantité modifiée ou un article ajouté revient au prix catalogue, et un devis expiré n'a plus d'effet. La commande et le paiement suivent le parcours normal ; **la facture n'existe qu'après le paiement**.
6. **Commande passée** : le devis passe à « Commandé », avec le numéro de commande.

**Cloisonnement** : un client ne voit, n'actionne et ne télécharge que ses propres devis.

**Suivi** : chaque étape est datée et attribuée (demande, proposition et version, acceptation, mise au panier, commande, expiration).

### Écrans

- **M30 « Mes devis »** (`/devis`, menu de l'espace client sous « Mes commandes ») :
  - indicateurs cliquables (tous, en attente, acceptés, refusés, brouillons) et recherche ;
  - « Demander un devis » ;
  - tableau (HT, TVA, TTC, statut, validité, PDF) ;
  - détail, suivi daté, actions ;
  - mention « Acceptation d'un devis ≠ facture ».
- **Panier** : encart de demande de devis.
- **Administration** : grille native des devis et fiche de traitement (proposition, refus, PDF, suivi). Le droit « Proposer ou refuser » est séparé.

## 2. Fichiers

**`app/code/Myca/Quote/`**
- `Model/QuoteManagement` : seul point d'écriture.
- `Model/QuoteStatus` : états et droits.
- `Model/QuoteCalculator` : HT, TVA, TTC, validité.
- `Model/PdfRenderer` : PDF avec polices embarquées, accents et symbole €.
- `Model/CustomerQuotes` : cloisonnement.
- Modèle, ressource et collection.
- `Observer/ApplyAgreedPrices` : prix convenus au panier.
- `Observer/MarkQuoteOrdered`.
- `Cron/ExpireQuotes`.
- Contrôleurs vitrine et administration, `ViewModel/`.
- `etc/` : schéma (3 tables, plus la colonne `myca_quote_id` du panier), ACL, menu, routes, tâche planifiée.
- Tests.

**Thème**
- `Myca_Quote/` : lien de menu, M30, encart du panier.
- `Magento_Checkout/layout/checkout_cart_index.xml`.
- Icône du menu dans `Magento_Customer/templates/account/navigation.phtml`.

**Commandes** : `bin/magento module:enable Myca_Quote`, `setup:upgrade`, `setup:di:compile`, build Tailwind, `cache:flush`. Le cron Magento doit tourner pour l'expiration.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 64 tests**. Nouveaux tests : totaux HT/TVA/TTC, dernier jour de validité inclus, droits du client et de MYCA |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal : M30 et panier aux 4 largeurs | **Aucun** |

Contrôles sur l'environnement local (navigateur, et scripts `var/tmp/myca-demo/quote-*.php` hors dépôt) :

| Scénario | Résultat |
|---|---|
| Demande envoyée depuis le panier (50 casquettes + message) | DEV-2026-0001 « Demande envoyée » |
| Proposition à 9,50 €, puis nouvelle proposition à 9,20 € (saisie « 9,20 ») | Versions 1 et 2 ; la v1 reste sur le disque ; 460,00 € TTC = 383,33 € HT + 76,67 € de TVA |
| PDF | En français, conforme à l'empreinte ; **altéré → refusé** |
| Acceptation, puis « Transformer en commande » | Panier à **460,00 €** (9,20 €/pièce) |
| 49 pièces au lieu de 50, puis retour à 50 | 29,90 € (prix catalogue), puis 9,20 € |
| Commande passée | 000000026, état « nouvelle », **0 facture** ; devis « Commandé » |
| Acceptation après la validité | Refusée ; devis « Expiré » |
| Tâche d'expiration, puis rejouée | 2 devis expirés, puis 0 |
| Autre compte : devis, actions, PDF | Rien de visible ; « introuvable » ; PDF refusé |

Captures : `docs/etape-6-devis/` (M30 et panier aux 4 largeurs).

## 4. Écarts avec Figma et le cahier des charges

1. **Commande de bout en bout dans le navigateur** : impossible tant que la licence Hyvä Checkout manque. La commande a été passée par le service Magento qu'utilise le tunnel.
2. **Filtres « Période » et « Collection »** : remplacés par la recherche et les indicateurs cliquables. L'attribut collection n'existe pas sur les variantes de démo.
3. **Bandeau** : les 3 promesses de la maquette (« Réponse sous délai validé », etc.) ne sont pas affichées, aucun délai n'étant validé.
4. **Notifications par e-mail** (demande reçue, proposition disponible) : non envoyées (pas de serveur de messagerie local, modèles à fournir).
5. **Taille des PDF** : environ 2,5 Mo par version, car Magento embarque les polices complètes. Une police allégée réduirait ce poids (V4).
6. **Devis pour les boutiques partenaires** : non prévu par la maquette. Le module fonctionne pour tout compte client, mais l'espace partenaire n'a pas d'écran devis.

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Durée de validité par défaut** (préremplie à 30 jours dans l'administration) et délai de réponse annoncé au client. |
| V2 | **Minimum de quantité** pour demander un devis, ou devis possible dès 1 pièce ? |
| V3 | **Personnalisation** (broderie, logo) : simple message libre, ou options structurées avec fichiers joints ? |
| V4 | Mise en page du PDF (logo, mentions légales et SIRET de MYCA, conditions de paiement) et police allégée. |
| V5 | Les boutiques partenaires doivent-elles aussi pouvoir demander des devis ? |
| V6 | Questions des étapes précédentes, en particulier la **licence Hyvä Checkout**. |
