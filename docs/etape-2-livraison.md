# MYCA — Étape 2 : pages catalogue (P01–P05, fiche produit)

Livré le 22/11/2026. **Point de validation : STOP.** L'étape 3 (panier, tunnel de commande, `Myca_Shipping`) attend votre accord.

## 1. Décision prise pendant l'étape

**Éditeur de pages : Page Builder natif de Magento** (et non Magezon). Magezon est payant, sa compatibilité Hyvä n'est pas confirmée (une fiche Marketplace indique « not compatible with Hyva ») et il impose jQuery/RequireJS en vitrine, ce que theme.md exclut. Page Builder est inclus dans Magento 2.4.9 et pris en charge par Hyvä.

## 2. Ce qui est livré

| Écran | Page | Rendu |
|---|---|---|
| P01 | Accueil | Page CMS **entièrement éditable dans Page Builder** : bannière, tuiles de réassurance, catégories, collections, Best-sellers, histoire, Nouveautés, encarts, newsletter, note légale |
| P02 | Nos collections (`/collections.html`) | Bandeau logo + titre serif, cartes des capsules et de MYCA Signature |
| P03 | Capsule (`/collections/espana-hidalgo.html`) | Bannière photo, récit de la collection (description éditable dans Page Builder), filtres, grille |
| P04 | Liste produits (`/polos.html`) | Titre + « N modèles · accroche », filtres Collection / Taille / Couleur / Prix, pastilles de filtres actifs, tri, pagination |
| P05 | Recherche | Titre « Résultats pour « … » », champ de recherche, raccourcis de filtres, grille ; état « aucun résultat » avec alternatives et recherches populaires |
| M45 / M47 / M50 | Fiche produit | Collection, titre, accroche, prix, avis, couleur, taille, disponibilité, « Acheter maintenant », favori, tuiles de réassurance ; onglets Description / Passeport & certificat / Matières & entretien |

**Responsive** (theme.md) : grilles 2 colonnes en mobile, 3 en tablette, 4 en desktop ; filtres dans un tiroir « Filtrer » avec bouton fixe en bas d'écran et nombre de résultats ; bouton d'achat fixe en bas d'écran sur la fiche produit ; zones tactiles de 44 px ; champs en 16 px en mobile.

## 3. Fichiers créés ou modifiés

**`app/code/Myca/Catalog/`** (nouveau module)
- `Setup/Patch/Data/AddProductAttributes.php` : `myca_collection`, `myca_best_seller`, `myca_color` (pastilles couleur), `myca_size` (pastilles texte S → 3XL), `myca_tagline`, `myca_materials`.
- `Setup/Patch/Data/AddCategoryAttributes.php` + `view/adminhtml/ui_component/category_form.xml` : textes éditoriaux des collections (étiquette, surtitre, titre et texte de bannière, accroche, affichage sur « Nos collections »), groupe « MYCA » dans l'admin.
- `Setup/Patch/Data/CreateCategoryTree.php` : Collections (5 capsules), Polos, Tee-shirts, Sweats, Casquettes, MYCA Signature, avec les textes des maquettes.
- `Setup/Patch/Data/SetFilterPositions.php`, `EnableBestSellerCondition.php`.
- `ViewModel/ProductVariants.php` (pastilles et tailles des cartes), `CategoryCards.php`, `SearchSuggestions.php`.
- `Block/Widget/CategoryCards.php` + `etc/widget.xml` + gabarit : widget « MYCA – Cartes de catégories », insérable dans Page Builder.

**`app/code/Myca/Content/`** (nouveau module)
- `Setup/Patch/Data/CreateHomePage.php` + `Setup/content/home.html` : page d'accueil au format Page Builder ; photos du Figma converties en WebP (`Setup/content/media`).
- `Block/Widget/Newsletter.php` + `etc/widget.xml` + gabarit : widget « MYCA – Inscription à la newsletter » (textes modifiables).

**`app/code/Myca/Core/`** : pastilles « Rupture » et « −20 % » ajoutées à `ProductBadge` (avec tests).

**Thème `Myca/default`**
- Catalogue : `Magento_Catalog/templates/category/{header,collections,collection-card}.phtml`, `product/list.phtml`, `product/list/toolbar.phtml`, `product/view/{product-info,myca-tabs,addtocart,addtowishlist,stock-status,breadcrumbs}.phtml`, mises en page `catalog_category_view*.xml`, `catalog_product_view.xml`.
- Filtres : `Magento_LayeredNavigation/templates/layer/{view,filter,state,chips}.phtml`, `Magento_Swatches/templates/product/layered/renderer.phtml`.
- Recherche : `Magento_CatalogSearch/templates/result.phtml`, `layout/catalogsearch_result_index.xml`.
- Accueil : `Magento_Theme/layout/cms_index_index.xml` (retire les blocs de démonstration Hyvä), `Magento_CatalogWidget/.../grid.phtml`.
- Styles : `web/tailwind/myca/layout.css`, `web/tailwind/myca/page-builder.css` (classes `myca-pb-*` à poser dans Page Builder).
- Fil d'Ariane, traductions `i18n/fr_FR.csv`, icônes Figma supplémentaires.

**Environnement** : extension PHP `igbinary` ajoutée à l'image Docker (le cache la déclare dans `env.php` ; sans elle, le vidage du cache ne fonctionnait pas).

## 4. Commandes

```bash
docker compose build php && docker compose up -d
docker compose exec php bin/magento setup:upgrade
docker run --rm -v "$PWD":/var/www/html -w /var/www/html/app/design/frontend/Myca/default/web/tailwind node:20-slim sh -c "npm ci && npm run build"
docker compose exec php bin/magento indexer:reindex
docker compose exec php bin/magento media-gallery:sync
docker compose exec php bin/magento cache:flush
```

## 5. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca` | **OK — 10 tests, 16 assertions** |
| PHPStan niveau 8 sur `app/code/Myca` | **0 erreur** |
| PHPCS Magento2 (modules + thème) | **0 erreur** (avertissements de longueur de ligne sur les classes Tailwind) |
| Débordement horizontal à 375 / 768 / 1280 / 1600 px | **Aucun**, sur les 7 pages |
| Accueil ouvert dans Page Builder (admin) | Contenu reconnu élément par élément (rangées, colonnes, titres, textes, boutons, images, produits, widgets) ; largeurs de colonnes lues correctement |
| Tiroir de filtres mobile | S'ouvre, le focus passe sur le titre, bouton « Afficher N résultats » |

Captures dans `docs/etape-2/` : `p01` à `p05`, `p05-vide`, `m45` aux quatre largeurs, et `p04-375-filtres.png`.

Pour les contrôles, un **catalogue de démonstration local** (12 produits configurables, noms, prix et photos des maquettes) a été créé dans la base locale uniquement ; il n'est pas dans le code. Un compte d'administration local de test (`claude-test`) a aussi été créé pour ouvrir Page Builder : **à supprimer** (*Système > Utilisateurs*) quand vous n'en avez plus besoin.

## 6. Écarts avec Figma et le cahier des charges

1. **P03 (capsule)** : les filtres sont dans la colonne latérale comme en P04, et non en listes déroulantes horizontales. Un seul modèle de filtres pour toutes les listes ; à valider (V1).
2. **Filtres « Nouveautés uniquement » et « Disponible en stock »** (P04) : non natifs dans Magento, non livrés. Les produits épuisés sont déjà masqués des listes ; à confirmer (V2).
3. **Filtres multiples dans un même groupe** (deux collections à la fois) : Magento Open Source n'autorise qu'une valeur par filtre. Les cases à cocher se comportent donc comme des liens (V3).
4. **Tri « Popularité »** : non natif ; le tri par défaut s'appelle « Pertinence » (position définie en admin) (V4).
5. **Libellé de collection** sur les cartes : « España – Hidalgo » (nom de l'option) au lieu de « Collection España ».
6. **Photos** : ce sont celles du Figma (captures basse définition, notamment la boutique en 148 px). Les visuels HD restent **À FOURNIR**.
7. **Onglet « Passeport & certificat »** : texte de présentation et lien. Le passeport réel (QR code, traçabilité) relève de `Myca_Passport` (lot 2).
8. **« (128 avis clients vérifiés) »** : l'avis s'appuie sur les avis Magento natifs ; le formulaire « Donner votre avis » est conservé sous les onglets (absent du Figma) (V5).
9. **Pages éditoriales liées** (`notre-histoire`, `devenir-partenaire`, `passeport`, `confidentialite`…) : liens prêts, pages à créer (M41–M52, P14–P21) dans une étape ultérieure.

## 7. Points à valider

| # | Question |
|---|---|
| V1 | Filtres de P03 : colonne latérale (livrée) ou listes déroulantes horizontales comme la maquette ? |
| V2 | Faut-il développer les filtres « Nouveautés uniquement » et « Disponible en stock » ? |
| V3 | La sélection de plusieurs valeurs dans un même filtre est-elle indispensable (extension ou développement spécifique) ? |
| V4 | Quel critère pour « Popularité » : ventes, nombre de vues ? |
| V5 | Conserver le formulaire d'avis sur la fiche produit ? |
| V6 | Groupes clients « partenaire », liens des réseaux sociaux, pack de langue français, licence Hyvä Checkout (questions de l'étape 1, toujours ouvertes). |

## 8. Pour modifier l'accueil

*Contenu > Pages > Accueil > Modifier avec Page Builder.* Chaque bloc se déplace, se duplique ou se supprime. Les styles MYCA s'appliquent par le champ **Classes CSS** de chaque élément :

| Classe | Effet |
|---|---|
| `myca-pb-hero` (rangée) | Bannière bleu nuit avec photo à droite |
| `myca-pb-tiles` (rangée) + `myca-pb-tile myca-pb-icon-truck` (texte) | Tuiles de réassurance avec icône (`truck`, `refresh`, `lock`, `qr`, `award`, `shield-check`) |
| `myca-pb-products` (rangée) | Titre + bouton « Voir tout » + liste de produits |
| `myca-pb-story-text` (colonne) | Encadré « Notre histoire » |
| `myca-pb-promo` (colonne) | Encart sombre avec image à droite |
| `myca-pb-note` (texte) | Note d'information bleue |
| `myca-pb-arrow` (bouton) | Flèche devant le libellé |

Les sections « Acheter par catégorie », « Nos collections » et « Rejoignez le cercle MYCA » sont des widgets MYCA. Leurs textes et leurs catégories se modifient dans la fenêtre du widget.
