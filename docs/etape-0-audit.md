# MYCA — Étape 0 : audit et plan

Sources lues : maquette Figma « MYCA — Design » (page *Couverture & Index* et page *Design System*, variables de la section *Fondations*).
Cahier des charges PDF lu le 22/11/2026 (148 pages ; copie valide dans `~/Documents/myca/` — celle du dossier du projet est vide, 0 octet). Ce qu'il apporte est résumé en section 2 bis.
Aucun code n'a été écrit. Ce document attend votre validation avant l'étape 1.

## 1. Existant

- Magento Open Source **2.4.9** installé en local (Docker : PHP 8.5, MySQL 8.4, OpenSearch 2.19, nginx sur le port 8088). Ni Redis ni Varnish pour l'instant.
- Thème actif : Luma. Aucun code `Myca`, aucun thème Hyvä installé.
- Le dépôt GitHub d'origine (`Auxioma/myca-collection`) a diffusé un malware. Sa branche `main` le contient toujours (voir Q2).

## 2. Ce que contient la maquette

**Pages** : Couverture & Index · Design System · Administration (M01–M15) · Partenaires B2B (M16–M27) · Client B2C (M28–M40) · Site public (M41–M52 et P01–P21) · Prototype.

**Design System** :
- **Couleurs** : palettes navy, blue, red, green, gold, amber et gray.
- **Rôles sémantiques** :
  - textes : `text/primary`, `text/secondary`, `text/muted`, `text/inverse` ;
  - surfaces et bordures : `surface/page`, `surface/card`, `surface/subtle`, `border/default`, `border/strong` ;
  - statuts : `status/*` (fond et texte pour success, warning, info, danger, neutral, premium) ;
  - marque : `brand/red #d71920`, `brand/navy #0f1b35`, `brand/gold #d4a537` ;
  - graphiques : `chart/1` à `chart/5` ;
  - accent : `accent/bg`, `accent/fg`, `accent/soft`, `accent/text`.
- **Espacements** : 4, 8, 12, 16, 20, 24 et 40. **Rayons** : 4, 8, 12, 16 et 999 (forme pilule).
- **Typographie** :

| Style | Police | Taille / interligne |
|---|---|---|
| Display/Hero | Playfair Display Bold Italic | 36/44 |
| Display/Section | Playfair Display Bold Italic | 24/30 |
| Display/Logo | Playfair Display Bold | 26/28 |
| Heading/H1 | Inter Bold | 26/32 |
| Heading/H2 | Inter Bold | 18/24 |
| Heading/H3 | Inter Semi Bold | 15/20 |
| KPI/Value | Inter Bold | 22/28 |
| Body/M | Inter | 14/20 |
| Body/S | Inter | 13/18 |
| Body/S Strong | Inter Semi Bold | 13/18 |
| Button | Inter Semi Bold | 14/20 |
| Table/Header | Inter Semi Bold | 12/16 |
| Caption | Inter | 12/16 |
| Caption Strong | Inter Semi Bold | 12/16 |
| Overline | Inter Bold | 12/16, espacement 12 |

- **Composants** : Logo, Bouton, Badge statut, Champ, Case à cocher, Interrupteur, Onglet, Onglet ligne, Élément de navigation, Avatar, Drapeau, Étape de suivi. S'y ajoutent les composants de structure (en-têtes, menus, pieds de page) et les bandeaux de page.
- **Icônes** : lucide, trait de 2 px.
- **Photos** : extraites de captures WhatsApp, marquées dans Figma « à remplacer par les HD ».

Points relevés :
- Les variables lues sont celles du mode **admin** (accent bleu). Je lirai les modes partenaire, client et public à l'étape 1.
- L'index Figma signale déjà des corrections de contenu : « livraison offerte » sur 69 €, « 30 jours », « gratuite en Europe », « 48 h ». Elles correspondent aux libellés interdits de theme.md.

## 2 bis. Ce qu'impose le cahier des charges

Le document se compose d'un avenant prioritaire du 8 novembre 2026 (52 maquettes) et d'un cahier des charges de 92 pages du 4 novembre 2026. En cas de contradiction, l'ordre de priorité est le suivant : l'avenant, puis les règles métier, puis les autres fiches, puis les maquettes.

**Conditions contractuelles**
- **Prix forfaitaire : 1 500 € net TTC**, design Figma et développement inclus.
- **Échéance impérative : 31 décembre 2026**, avec toutes les fonctionnalités opérationnelles et testées. Une démonstration ne vaut pas livraison.
- **Figma** : il faut livrer des fichiers éditables en desktop, tablette et mobile, avec les états des composants et des prototypes navigables (B2C, B2B, admin). La maquette actuelle n'existe qu'en desktop.
- **Livrables** : code source, migrations, documentation, accès administrateur, comptes de services au nom du commanditaire, environnement de production, procédures de sauvegarde et de restauration, tests de recette.

**Règles métier qui répondent à des questions de l'audit**
- **Dropshipping (Q9)** : la boutique encaisse son propre client et reste le vendeur. MYCA fournit la logistique et les documents interentreprises.
- **« Après remise » (Q7)** : le seuil s'applique aux produits éligibles, après remises et hors frais de livraison. Les tests portent sur 99,99 €, 100 € et 100,01 €, ainsi que sur une remise qui fait passer le panier sous 100 €.
- **Paiement (Q5)** : carte bancaire via un prestataire de paiement. PayPal et les autres moyens seulement après validation. Webhooks signés et idempotents, aucune donnée de carte stockée. Le prestataire n'est pas nommé.
- **Rôles (Q10)** : client, boutique, support, comptable, logistique, superadmin.
- **Langues (Q13)** : le français est la langue de référence. L'architecture doit être prête pour EN, ES, DE, IT, NL et PT, avec des URL localisées et hreflang. Les langues réellement livrées doivent être listées dans le contrat.
- **Stock** : réservations atomiques par variante. La propagation entre les trois espaces doit prendre au plus 1 seconde, mesurée à la recette.
- **Identités** : client vert, partenaire rouge, administration bleue. Theme.md ajoute un site public rouge sur fond bleu nuit, ce qui est cohérent avec Figma.
- **Retours à 30 jours** : à ne pas annoncer tant que la politique commerciale n'est pas validée.
- Le cahier des charges **n'impose ni Magento ni Hyvä**. Ce choix vient de theme.md.

**Écart à signaler avant tout engagement**
- Le périmètre couvre trois espaces, le stock en temps réel, le dropshipping, les retours, la fidélité, les devis, le passeport QR, la production, le multilingue et un Figma responsive complet.
- À l'échelle du marché, ce périmètre représente plusieurs mois de travail pour une équipe, soit un budget bien supérieur à 1 500 €.
- Le délai restant est d'environ 5 semaines.
- Le forfait et l'échéance ne sont donc pas tenables tels quels. L'avenant demande d'ailleurs que le chiffrage soit « expressément confirmé par le prestataire ».

## 3. Arborescence prévue

```
app/design/frontend/Myca/default/
├── registration.php, theme.xml (parent : Hyva/default), composer.json
├── etc/view.xml                      tailles d'images, srcset
├── web/tailwind/                     config Tailwind générée depuis les variables Figma
├── web/css/                          @layer components : .myca-hero, .myca-h1…
├── web/fonts/                        Inter, Playfair Display (woff2, hébergées localement)
├── web/images/                       logo, favicon, icônes SVG lucide
├── Magento_Theme/
│   ├── layout/default.xml            en-tête, bandeau de réassurance, pied de page
│   └── templates/
│       ├── html/header.phtml, footer.phtml, breadcrumbs.phtml, reassurance.phtml
│       └── elements/button.phtml, badge.phtml, field.phtml, tabs.phtml
├── Magento_Catalog/                  carte produit, liste, fiche produit à onglets
├── Magento_Checkout/                 panier, en-tête épuré du tunnel
├── Magento_Customer/                 connexion, espace client, menu latéral / mobile
├── Magento_Cms/                      gabarits des pages CMS
└── i18n/fr_FR.csv, en_US.csv

app/code/Myca/
├── Core/  Partner/  Dropship/  Shipping/  Rma/        lot 1
├── PartnerSales/  Loyalty/  Quote/  Passport/         lot 2
└── Production/                                        lot 3
```

Chaque module contient `registration.php`, `etc/module.xml`, `etc/db_schema.xml`, `etc/acl.xml`, `etc/adminhtml/system.xml`, `Api/` (contrats de service), `Model/`, `Plugin/`, `Test/Unit` et `Test/Integration`, ainsi que `i18n/`.

## 4. Correspondance écran → mise en page et gabarit

| Écrans | Handle de mise en page | Gabarits du thème |
|---|---|---|
| P01 Accueil | `cms_index_index` | page CMS + widgets (héros, collections, réassurance) |
| P02–P04 Collections, capsule, liste | `catalog_category_view` | `Magento_Catalog/product/list.phtml`, carte produit, filtres (tiroir sur mobile) |
| P05 Recherche | `catalogsearch_result_index` | réutilise la liste produits |
| M45, M47, M50 Fiche produit | `catalog_product_view` | galerie, onglets Description / Passeport / Matières |
| P06 Panier | `checkout_cart_index` | panier + message « plus que X € » (`Myca_Shipping`) |
| P07–P10 Tunnel | `checkout_index_index`, `checkout_onepage_success` | dépend de Q4 |
| P11–P13 | `customer_account_login`, `customer_account_create`, `customer_account_forgotpassword`, `sales_guest_form` | formulaires |
| M28–M40 Espace client | `customer_account_index`, `sales_order_history`, `wishlist_index_index`, `review_customer_index`, `customer_address_index`… | menu latéral → liste de rubriques sur mobile |
| M16–M27 Espace partenaire | routes `myca_partner/*` (front) | même gabarit de compte, identité « partenaire » |
| M41–M44, M46, M48, M49, M51, M52, P14–P21 | `cms_page_view`, `cms_noroute_index` | gabarit de page CMS |
| M01–M15 Administration | grilles et formulaires UI components | pas de reproduction visuelle |

L'identité visuelle (client, partenaire ou public) est portée par une classe sur `<body>`. Un bloc la calcule à partir du groupe client.

## 5. Tables des modules du lot 1

**Myca_Core**
- `myca_audit_log` : journal immuable, sans aucune mise à jour.
  - Colonnes : log_id, actor_type, actor_id, action, entity_type, entity_id, payload (JSON), ip, created_at.
- `myca_support_ticket` : ticket_id, customer_id, partner_id, subject, status, priority, created_at, updated_at.
- `myca_support_message` : message_id, ticket_id, author_type, author_id, body, created_at.

**Myca_Partner**
- `myca_partner` : partner_id, code, company_name, siret, vat_id, status, customer_group_id, created_at, approved_at, suspended_at.
  - Valeurs possibles de status : pending, active, suspended, rejected.
- `myca_partner_user` : partner_id, customer_id (unique), role.
- `myca_partner_application` : candidature P17, avec status, reviewed_by et reviewed_at.
- `myca_partner_order` : order_id, partner_id.
  - Cloisonnement : des plugins sur les dépôts de commandes, factures et clients filtrent sur partner_id. Les tests d'intégration le vérifient.

**Myca_Dropship**
- `myca_dropship_order` : order_id, partner_id, mode, ship_to_end_customer, packing_slip_hide_prices.
  - Valeurs possibles de mode : dropship, stock.
- Les mentions et le logo du vendeur réel sont stockés sur `myca_partner`. Ils servent aux documents B2B (M17).

**Myca_Shipping**
- `myca_shipping_destination` : country_id, relay_enabled, home_enabled, is_active.
- `myca_shipping_rate` : rate_id, scope, country_id, weight_from, weight_to, price.
  - Valeurs possibles de scope : b2c_home, b2b.
- Configuration :
  - seuil de 100,00 € TTC, calculé après remise ;
  - attribut produit « éligible point relais ».
- Un plugin sur la validation du paiement bloque la commande si le pays n'est pas desservi ou si l'API transporteur est indisponible.

**Myca_Rma**
- `myca_rma` : rma_id, increment_id, order_id, origin, status, customer_id, partner_id, refund_party, policy_days, created_at, deadline_at.
  - Valeurs possibles de origin : online, dropship, store.
  - Valeurs possibles de refund_party : myca, partner, none.
- `myca_rma_item` : rma_item_id, rma_id, order_item_id, qty, condition, resolution.
- `myca_rma_history` : historique des statuts.
- Délai : 14 jours par défaut. 30 jours uniquement si le paramètre de configuration est activé.

## 6. Questions ouvertes (à valider avant l'étape 1)

| # | Question | Pourquoi |
|---|---|---|
| Q1 | ~~PDF du cahier des charges~~ **Résolu** : lu (voir 2 bis). Q5, Q7, Q9, Q10 et Q13 sont résolues en partie par ce document. | — |
| Q1 bis | Acceptez-vous le forfait de 1 500 € et l'échéance du 31/12/2026 tels quels, ou faut-il d'abord renégocier le périmètre par avenant ? | L'écart entre le périmètre et le budget est majeur (voir 2 bis). |
| Q2 | Le projet doit-il partir d'un **nouveau dépôt propre** plutôt que de `Auxioma/myca-collection` ? Qui est le client final ? | La branche `main` d'origine contient un malware. |
| Q3 | Quelle version de Hyvä ? Avec Tailwind 3 ou 4 ? | Je dois aussi vérifier que cette version est compatible avec Magento 2.4.9 et PHP 8.5 avant de l'installer. |
| Q4 | Page de commande : Hyvä Checkout (payant) ou checkout Luma en mode compatibilité ? | Ce choix détermine les gabarits des étapes P07 à P10. |
| Q5 | Prestataire de paiement : Stripe ou Adyen ? | Il conditionne M35, le coffre-fort des moyens de paiement. |
| Q6 | Quels transporteurs, et quelle API interroger pour savoir si le transporteur est « indisponible » ? | C'est la règle de blocage du paiement de `Myca_Shipping`. |
| Q7 | « Après remise » : cela inclut-il les points fidélité utilisés, et pas seulement les règles panier et les codes promo ? | Le seuil des 100 € en dépend. |
| Q8 | Quels pays desservis au lancement ? Quels sont les barèmes domicile et B2B ? | Ce sont les données de configuration initiales. |
| Q9 | Qui facture le client final en dropshipping : la boutique ou MYCA pour le compte de la boutique ? | Cela fixe le modèle des documents (M17). |
| Q10 | Quels rôles délégués pour un partenaire (exemple : gérant, vendeur, comptable) ? | Ils déterminent les droits de `myca_partner_user`. |
| Q11 | « Overline » a un espacement de 12 : faut-il lire 12 % ou 12 px ? | La valeur est ambiguë dans Figma. |
| Q12 | Photos HD, coordonnées légales réelles, données textiles du fabricant ? | Elles sont marquées « à fournir » dans Figma. |
| Q13 | Faut-il une boutique en anglais en plus du français ? | theme.md demande `en_US.csv`. |
| Q14 | Faut-il ajouter Redis et Varnish à l'environnement local dès l'étape 1 ? | theme.md les cite. Ils ne sont pas installés aujourd'hui. |

## 7. Suite proposée

L'étape 1 démarrera une fois les questions Q1 à Q4 tranchées. Elle comprend :
- l'installation de Hyvä ;
- le squelette du thème ;
- la configuration Tailwind générée depuis les variables Figma, dans les 4 modes ;
- l'en-tête, le pied de page, les boutons et la carte produit ;
- une capture de chaque élément à 375, 768, 1280 et 1600 px.

**STOP — en attente de validation.**
