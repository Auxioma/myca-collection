# MYCA — Étape 1 : thème, jetons Figma, en-tête, pied de page, composants

Livré le 22/11/2026. **Point de validation : STOP.** L'étape 2 (pages catalogue) attend votre accord.

## 1. Environnement

| Élément | Version |
|---|---|
| Magento Open Source | 2.4.9 (PHP 8.5, MySQL 8.4, OpenSearch 2.19, Docker) |
| Hyvä Theme | **1.5.4** (dernière version, gratuite et open source, installée depuis les dépôts GitHub publics de Hyvä, sans clé) |
| Tailwind CSS | **4.3** (configuration en CSS, sans `tailwind.config.js`) |
| Thème actif | `Myca/default`, enfant de `Hyva/default` |

Le projet a été déplacé hors d'iCloud dans `~/Projects/myca-collection` (iCloud rendait des fichiers illisibles pour Docker).

## 2. Fichiers créés ou modifiés

**Thème `app/design/frontend/Myca/default/`**

| Fichier | Rôle |
|---|---|
| `registration.php`, `theme.xml`, `composer.json` | Déclaration du thème enfant de Hyvä |
| `web/tailwind/myca/tokens.css` | Variables Figma → Tailwind : palettes, rayons, ombre « Carte », points de rupture, conteneur 1200 px, couleurs sémantiques par identité |
| `web/tailwind/myca/components.css` | Styles de texte Figma (`.myca-hero`, `.myca-h1`…) et composants : bouton, badge, champ, onglets, fil d'Ariane, carte |
| `web/tailwind/myca/fonts.css`, `web/fonts/*.woff2` | Inter et Playfair Display hébergées localement (licence OFL jointe) |
| `web/tailwind/tailwind-source.css`, `hyva.config.json` | Point d'entrée Tailwind ; rattache les couleurs Hyvä (`primary`…) à l'identité MYCA |
| `web/tailwind/{base,components,theme,utilities}/`, `package*.json` | Copie standard du parent Hyvä (point de départ d'un thème enfant) |
| `web/css/styles.css` | CSS compilé |
| `web/svg/*.svg` | 14 icônes lucide exportées du Figma (trait recoloré en `currentColor`) |
| `Magento_Theme/layout/default.xml` | Bandeau de réassurance, burger à gauche en mobile, logo du pied de page, suppression des blocs Hyvä inutiles |
| `Magento_Theme/templates/html/header.phtml` | En-tête public (barre sombre + icônes) |
| `Magento_Theme/templates/html/header/logo.phtml` | Logo MYCA typographique (en-tête et pied de page) |
| `Magento_Theme/templates/html/header/reassurance.phtml` | Bandeau de réassurance |
| `Magento_Theme/templates/html/header/menu/desktop.phtml`, `mobile.phtml` | Menu principal et tiroir mobile |
| `Magento_Theme/templates/html/footer.phtml` | Pied de page |
| `Magento_Customer/templates/header/customer-menu.phtml` | Icône compte |
| `Magento_Catalog/templates/product/list/item.phtml`, `wishlist/button.phtml` | Carte produit et bouton favori |

**Module `app/code/Myca/Core/`** (règles hors du thème)

- `Model/Identity.php` + `Observer/AddIdentityBodyClass.php` : classe de `<body>` déduite du groupe client — `myca-public` (visiteur), `myca-client` (client connecté), `myca-partner` (groupes choisis en admin : *Magasins > Configuration > MYCA > Identité visuelle*). S'appuie sur le contexte HTTP, compatible avec le cache de page.
- `ViewModel/ProductBadge.php` : pastille « Best-seller » (attribut `myca_best_seller`) prioritaire sur « Nouveau » (dates natives « Nouveau produit »).
- `etc/acl.xml`, `etc/adminhtml/system.xml`, `i18n/fr_FR.csv`, `i18n/en_US.csv`, tests unitaires.

**Racine** : `composer.json` / `composer.lock` (Hyvä 1.5.4 ajouté sans mettre à jour les autres paquets ; Mollie exclu ; plugin d'audit Magento désactivé car il bloquait l'installation), `app/etc/config.php`, `app/etc/hyva-themes.json`.

## 3. Commandes

```bash
docker compose exec php composer install
docker compose exec php bin/magento setup:upgrade
# build Tailwind (dans un conteneur Node, rien n'est exécuté sur le Mac)
docker run --rm -v "$PWD":/var/www/html -w /var/www/html/app/design/frontend/Myca/default/web/tailwind node:20-slim sh -c "npm ci && npm run build"
docker compose exec php bin/magento cache:flush
```

En production : `setup:di:compile` et `setup:static-content:deploy fr_FR` en plus.

## 4. Tests et contrôles (résultats réels)

| Contrôle | Résultat |
|---|---|
| PHPUnit `app/code/Myca/Core/Test/Unit` | **OK — 8 tests, 10 assertions** |
| PHPStan niveau 8 sur `app/code/Myca` | **0 erreur** |
| PHPCS standard Magento2 (module + thème) | **0 erreur** ; avertissements de longueur de ligne sur les listes de classes Tailwind |
| Débordement horizontal (375 / 768 / 1280 / 1600) | **Aucun**, accueil et page de recherche |
| Identité client forcée | Barre vert foncé, bouton or texte vert : conforme au mode Figma « client » |

Captures dans `docs/etape-1/` : `home-*.png`, `recherche-*.png` (cartes produit), `identite-client-1280.png`.
Deux produits **de démonstration locaux** (Polo Hidalgo, Polo Westminster, données et images du Figma P01) ont été créés dans la base locale pour contrôler la carte. Ils ne sont pas dans le code et seront supprimés.

## 5. Écarts avec Figma et le cahier des charges

1. **Contenu de l'accueil** : bannière par défaut de Hyvä — la page P01 est prévue à l'étape 2.
2. **Menu principal** : seul « Accueil » s'affiche, car le catalogue n'a pas encore de catégories (Collections, Polos, Tee-shirts, Casquettes, MYCA Signature).
3. **Carte produit** : les pastilles de couleur et la ligne des tailles du Figma s'appuient sur des produits configurables ; elles seront vérifiées à l'étape 2 avec les vrais produits. La ligne « Collection España » s'affichera dès que l'attribut `myca_collection` existera.
4. **Textes Magento en anglais** (« Home », « Search results for ») : le pack de langue français n'est pas installé.
5. **Mobile** : le Figma n'a pas de maquettes mobiles. Choix faits selon theme.md : burger à gauche, réassurance une mention à la fois (défilement au doigt, sans animation automatique) jusqu'à 1024 px, pied de page en accordéon, zones tactiles de 44 px.
6. **Identité admin** (accent bleu) : non utilisée sur le site, elle concerne le back-office.
7. **Overline** : espacement des lettres interprété comme 12 % (0,12 em) — voir Q11.

## 6. Points à valider

| # | Question |
|---|---|
| V1 | Les captures `docs/etape-1/*` sont-elles conformes à vos attentes, notamment les choix mobiles (écart 5) ? |
| V2 | Adresses des liens du pied de page (`collections/espana-hidalgo.html`, `cgv`, `livraison`…) : provisoires, à confirmer ou remplacer. |
| V3 | Liens des réseaux sociaux (Facebook, Instagram, TikTok, YouTube, LinkedIn) : URL à fournir ; les icônes ne sont pas cliquables d'ici là. |
| V4 | Moyens de paiement affichés (Visa, Mastercard, PayPal, Apple Pay) : à confirmer selon le PSP retenu (Q5). |
| V5 | Quels groupes clients Magento correspondent aux **partenaires** (identité rouge) ? |
| V6 | Installer le pack de langue `fr_FR` (exemple : `mageplaza/magento-2-french-language-pack`) ou traduire nous-mêmes ? |
| V7 | Hyvä Checkout reste payant : la licence est nécessaire avant l'étape 3. |

## 7. Sécurité

La branche `dev` du dépôt GitHub `Auxioma/myca-collection` contient de nouveau le malware (commit `e995c18f`). Le projet local ne pointe plus vers GitHub. Il faut un nouveau dépôt privé, dont vous êtes propriétaire, avant tout nouvel envoi.
