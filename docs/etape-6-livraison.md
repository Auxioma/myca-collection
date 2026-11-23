# MYCA — Étape 6 : retours et SAV (`Myca_Rma`)

Livré le 23/11/2026. **Point de validation : STOP.** Les modules du lot 2 (`Myca_PartnerSales`, `Myca_Loyalty`, `Myca_Quote`, `Myca_Passport`) attendent votre accord.

Rappel : le tunnel de commande (P07–P10) reste bloqué par la licence Hyvä Checkout (étape 3).

## 1. Ce qui est livré

### Module `Myca_Rma`

**Trois origines de retour.** L'origine est déterminée par la commande et figée à la création de la demande.

| Origine | Qui rembourse le consommateur | Ce que fait MYCA |
|---|---|---|
| Achat MYCA en ligne | MYCA | Rembourse le payeur de la commande (avoir Magento) |
| Dropshipping partenaire | La boutique (elle le déclare dans son espace) | Retour logistique, puis avoir à la boutique |
| Stock de la boutique physique | Personne côté MYCA : le client retourne l'article au comptoir de la boutique | Aucun remboursement. Seul un dossier SAV (défaut) est accepté, avec un avoir si le défaut est reconnu |

Pour une commande partenaire dont le mode de vente n'a pas été déclaré, la boutique indique dans sa demande comment l'article a été vendu.

**Bon portail.**
- Un achat MYCA en ligne se retourne depuis l'espace client, ou sans compte via « Suivre ma commande ».
- Une commande de boutique se retourne depuis l'espace partenaire.
- Une demande déposée dans le mauvais espace est refusée avec un message qui indique où la faire.

**Délais.**
- Rétractation : **14 jours après la livraison** (passage de la commande au statut « Livrée »), jusqu'à minuit heure de Paris.
- Politique de 30 jours : **désactivée par défaut**, à activer en configuration après validation.
- SAV (défaut, article endommagé, non conforme) : délai séparé, 730 jours par défaut (garantie légale de 2 ans, à valider).
- Tant que la livraison n'est pas confirmée, le délai n'a pas commencé.

**Quantités.** On ne peut retourner que ce qui a été expédié, moins ce qui est déjà remboursé ou déjà demandé. Deux demandes simultanées sur la même pièce sont impossibles : la commande est verrouillée pendant la création.

**États.**
- Parcours : créée › en analyse › autorisée › en transit › réceptionnée › contrôlée › remboursée / échange expédié › clôturée.
- Fins possibles : refusée, annulée.
- Le demandeur peut seulement annuler sa demande avant l'envoi du colis, puis signaler qu'il l'a envoyé. Tout le reste relève de MYCA.

**Contrôle et stock.**
- Rien n'est remis en vente avant le contrôle : chaque article est noté revendable, défectueux (quarantaine) ou rebut.
- Seul un article revendable revient en stock, au moment du remboursement.
- Un article du stock d'une boutique ne revient jamais dans le stock central.

**Remboursement.**
- Un avoir Magento est créé pour les seuls articles retournés.
- Le port n'est remboursé que si un client B2C retourne toute sa commande (rétractation). Un retour partiel ne rembourse pas le port et ne le refacture pas.
- **Rejouer le remboursement ne crée jamais un second avoir** : la demande est verrouillée et l'avoir déjà émis est renvoyé.

**Historique.** Chaque état et chaque message est enregistré, avec son auteur et sa date. Le service client peut ajouter des notes internes, invisibles du demandeur.

**Configuration** : Magasins › Configuration › MYCA › Retours & SAV.

**Droits d'administration** :
- « Retours & SAV » : consulter ;
- « Traiter les demandes » ;
- « Rembourser / émettre un avoir », un droit séparé.

### Écrans

| Écran | Page | Contenu |
|---|---|---|
| M23 | `/partenaire/retours` | Indicateurs (demandes, retours, échanges, SAV, en cours, clôturées en %, avoirs MYCA) ; filtres ; recherche ; période ; tableau ; détail ; suivi en étapes avec dates réelles ; résumé du traitement ; actions rapides (voir la commande, annuler, colis remis au transporteur, « J'ai remboursé mon client », message) ; échanges avec le SAV ; mention des trois origines |
| M23 | `/partenaire/retours/demande` | Nouvelle demande, depuis « Mes commandes » (M19) › « Demander un retour / SAV » |
| M29 | « Mes commandes » (client) | Indicateur et filtre « Retours / SAV » ; bouton « Demander un retour » sur une commande éligible |
| — | `/retours` | « Mes retours & SAV » du client |
| — | `/retours/demande` | Formulaire de demande : type, articles et quantités, taille souhaitée pour un échange, motif, commentaire, dates limites |
| — | `/retours/voir` | Détail : suivi, règle de remboursement, actions, messages |
| Admin | Ventes › Opérations › Retours & SAV | Grille native (filtres par origine, type et statut) ; fiche de traitement (changement d'état, contrôle des articles, remboursement ou avoir, historique complet, notes internes) |

Le menu de l'espace partenaire et son pied de page affichent maintenant « Retours & SAV ».

## 2. Fichiers

**`app/code/Myca/Rma/`**
- `etc/` : schéma (`myca_rma`, `myca_rma_item`, `myca_rma_history`), routes, configuration, ACL, menu.
- `Model/` :
  - `ReturnPolicy` : origine, payeur, délais, port ;
  - `StatusFlow` : états et droits de passage ;
  - `OrderEligibility` : origine, date de livraison, quantités ;
  - `RmaManagement` : seul point d'écriture ;
  - `RequesterAccess` : client, client sans compte, boutique ;
  - `Reasons`, `Config`, `Rma`, ressource, collection et sources d'options.
- `Controller/` : pages et actions du client (`Index`, `Demande`, `Voir`), de la boutique (`Retours`) et de l'administration (`Adminhtml/Rma`).
- `ViewModel/` : `Returns` (vitrine) et `Admin`.
- `Ui/` et `view/adminhtml/` : grille et fiche de traitement.
- `Test/Unit/` : 3 fichiers de tests.

**Autres modules**
- `Myca_Dropship` : lecture du mode de vente d'une commande.
- `Myca_Partner` : rubrique « Retours & SAV » dans le menu. La rubrique active couvre aussi ses sous-pages.

**Thème `Myca/default`**
- `Myca_Rma/` : 5 mises en page, formulaire, pages client, M23, suivi et messages.
- Raccordements : `Magento_Sales/.../history.phtml` (M29), `Myca_Partner/templates/orders.phtml` (M19), pied de page partenaire, cartes KPI sur 7 colonnes.
- Icônes `wrench` et `swap`, dessinées au trait comme les autres (voir écart 7).

**Commandes à lancer** : `bin/magento module:enable Myca_Rma`, puis `setup:upgrade`, `setup:di:compile`, le build Tailwind et `cache:flush`.

## 3. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK : 55 tests, 114 assertions** (19 nouveaux) |
| PHPStan niveau 8, PHPCS Magento2 | **0 erreur** |
| Débordement horizontal : 6 pages × 4 largeurs | **Aucun** |

Les nouveaux tests unitaires couvrent :
- le payeur selon chacune des trois origines ;
- l'avoir MYCA ;
- les délais de 14 et 30 jours et de garantie ;
- la fin du délai à la seconde près ;
- le remboursement du port ;
- les droits du demandeur ;
- l'absence de remboursement avant contrôle ;
- le remboursement rejoué ;
- le mauvais portail, dans les deux sens ;
- la vente en boutique limitée au SAV.

Contrôles dans le navigateur et avec les vrais services Magento, sur les données de démonstration locales (`var/tmp/myca-demo/rma*.php`, hors dépôt) :

| Scénario | Résultat |
|---|---|
| Boutique : retour dropshipping sur sa commande | Demande créée |
| Boutique : commande d'une **boutique concurrente** (page et envoi forcé) | **Refusé** (« introuvable ») |
| Boutique : même pièce demandée deux fois | Refusé |
| Boutique : retour simple d'une vente en boutique | Refusé, avec renvoi au comptoir. Le dossier SAV est accepté, avec une description obligatoire |
| Boutique : commande non expédiée | Formulaire remplacé par un message |
| Boutique dans le portail client | Renvoyée vers l'espace partenaire |
| Client : retour dans le délai | Demande créée |
| Client : retour 16 jours après la livraison | **Refusé**. Le SAV reste possible |
| Client : échange sans taille souhaitée | Refusé |
| Client : demande ou commande d'un autre | « Introuvable » |
| Client : signaler l'envoi avant autorisation | Refusé. L'annulation fonctionne |
| Visiteur non connecté | Renvoyé vers la connexion ou vers « Suivre ma commande » |
| Remboursement avant contrôle | Refusé |
| Achat en ligne, article revendable | Avoir de 43,90 € (dont port 4,90 €, commande entière retournée) ; stock +1 |
| Dropshipping, article défectueux | Avoir boutique de 79,90 €, **sans port** ; stock inchangé (quarantaine) |
| **Remboursement rejoué** | Même avoir renvoyé ; **1 seul avoir** sur la commande |
| Administration : grille, fiche, changement d'état | OK |

Captures : `docs/etape-6/` (M23, nouvelle demande, M29, pages client, aux 4 largeurs ; fiche d'administration).

## 4. Écarts avec Figma et le cahier des charges

1. **Pas d'écran Figma** pour le formulaire et le suivi côté client. Ils reprennent les cartes de M29 et l'organisation du détail de M23.
2. **Remboursement réel par le prestataire de paiement** : l'avoir Magento est créé « hors ligne ». Le reversement sur la carte viendra avec le module de paiement (aucun prestataire n'est configuré).
3. **Échange** :
   - la taille souhaitée est enregistrée, mais elle **n'est pas réservée automatiquement** ;
   - la différence de prix et la nouvelle expédition sont traitées à la main ;
   - la mise à jour du passeport viendra avec `Myca_Passport`.
4. **Étiquette ou QR de retour transporteur** : non générés (API des transporteurs à choisir). Les instructions passent par le message d'autorisation.
5. **E-mails de notification** : non envoyés (pas de serveur de messagerie local, et modèles d'e-mails à fournir). L'historique et les messages sont visibles dans les espaces.
6. **Points fidélité au prorata** : avec `Myca_Loyalty` (lot 2).
7. **Icônes « échange » et « SAV »** : elles font partie des cartes KPI de la maquette, mais ne sont pas exportables séparément. Elles ont été redessinées au même trait.
8. **M23** :
   - les filtres « Collection » et « Statut » sont remplacés par les onglets et la recherche ;
   - le menu « ⋯ » des lignes n'est pas livré ;
   - « Valider l'échange » et « Effectuer un remboursement » relèvent de MYCA (administration), pas de la boutique.
9. **Retour sans compte** : le contrôle passe par le formulaire Magento « Suivre ma commande » (référence, nom, e-mail). Il n'a pas été testé dans le navigateur, faute de commande invité dans les données de démonstration.
10. **Date de livraison** : elle est lue dans l'historique du passage au statut « Livrée ». À défaut, on prend la date de dernière mise à jour de la commande ; la date limite est alors figée à la création de la demande.

## 5. Points à valider

| # | Question |
|---|---|
| V1 | **Politique de 30 jours** : on garde 14 jours (légal) seulement, ou on active 30 jours ? Il faut alors mettre à jour les CGV. |
| V2 | **Délai SAV** : 2 ans (garantie légale de conformité) pour tous, ou une règle différente pour les boutiques (garantie fournisseur) ? |
| V3 | **Avoir partenaire** : comprend-il les frais de port, de retour ou de dossier ? Selon quel contrat ? |
| V4 | **Frais de retour B2C** : à la charge du client ou offerts ? Il faut l'annoncer dans les CGV et dans l'information précontractuelle. |
| V5 | **Échange** : réserver automatiquement la nouvelle taille dès l'autorisation ? Que faire si elle est en rupture (remboursement, attente) ? |
| V6 | **Transporteur des retours** et génération d'étiquettes (lié à la question des transporteurs de l'étape 3). |
| V7 | **Livraison confirmée** : qui fait passer la commande à « Livrée » (question V2 de l'étape 4) ? Le délai de retour en dépend. |
| V8 | Questions des étapes précédentes toujours ouvertes, en particulier la **licence Hyvä Checkout**. |
