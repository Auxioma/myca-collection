# MYCA — Audit de conformité au cahier des charges

Audit du 24/11/2026 (J-37 avant l'échéance du 31/12/2026).

**Ce qui a été comparé** :
- le cahier des charges complet : l'avenant prioritaire du 8/11, les 64 fiches, les règles renforcées, les annexes A à F et les 52 maquettes ;
- l'état réel du projet : le code `app/code/Myca` et le thème, la base locale, les pages servies et les documents de livraison.

**Légende** : ✅ conforme · 🟡 partiel · ❌ absent · ⏸ bloqué par une décision ou une donnée du commanditaire.

**Suivi** : lot de conformité 1 (pages publiques et juridiques, cookies, SEO) livré le 25/11/2026 ; lot 2 (commande partenaire M17) et lot 3 (paiement Stripe, mode test) livrés le 25/11/2026. Voir `docs/lot-conformite-1-livraison.md` et `docs/lot-conformite-2-livraison.md`.

## 1. Verdict

**Le projet n'est pas encore conforme au cahier des charges.**

**Score** : sur 63 fiches fonctionnelles, **10 sont conformes**, **33 partielles** et **20 absentes**.

**Points solides** (conformes et testés) :
- le socle visuel (thème, jetons, responsive) ;
- le panier et la règle du point relais offert dès 100 € ;
- les retours selon les trois origines ;
- la fidélité ;
- les devis ;
- le passeport du vêtement ;
- la production et le contrôle qualité ;
- les comptes partenaires (candidature, validation, e-mails).

**Trous principaux** :
- le passage de commande partenaire (dropshipping et achat de stock) ;
- le paiement réel ;
- les pages publiques et juridiques : 13 liens du pied de page sur 22 sont en erreur 404 ;
- les cookies et le RGPD ;
- le multilingue ;
- les rôles d'administration, la MFA et le journal d'audit ;
- la logistique ;
- le support ;
- la recette finale.

## 2. Exigences contractuelles (avenant, pages prioritaires)

| Exigence | État | Constat |
|---|---|---|
| Figma éditable desktop, **tablette et mobile**, composants, états, prototypes navigables B2C/B2B/admin | ❌ | Le fichier Figma n'existe qu'en desktop (1600 px). Les déclinaisons mobile et tablette ont été faites directement dans le code. |
| Validation écrite des maquettes avant intégration | ⏸ | Aucune validation écrite n'est consignée. |
| Trois identités : client vert, partenaire rouge, admin bleu | 🟡 | Client et partenaire : faits. L'administration est l'interface Magento native, non habillée en bleu MYCA. |
| Livrables : code, migrations, documentation, accès, environnement de production, sauvegarde et restauration, tests de recette | 🟡 | Code, migrations et documentation par étape : faits. Manquent l'environnement de production, la procédure de sauvegarde et de restauration, le guide d'installation, le dépôt privé du commanditaire et le PV de recette. |
| Échéance du 31/12/2026, « toutes fonctionnalités opérationnelles et testées » | ❌ | Non atteignable sur tout le périmètre (voir §6). |
| Forfait de 1 500 € net TTC | ⏸ | L'écart entre ce forfait et le périmètre a été signalé à l'étape 0. L'avenant exige que le chiffrage soit « expressément confirmé par le prestataire ». |

## 3. Fiches fonctionnelles

### Fondations et interfaces

| Fiche | État | Constat |
|---|---|---|
| Vision et périmètre | 🟡 | La zone publique, l'espace client et l'espace partenaire existent. L'administration MYCA se limite aux écrans Magento natifs et aux écrans des modules. |
| Objectifs métier et indicateurs | ❌ | Il n'y a pas de tableau de bord ni d'indicateurs par canal, pays ou collection, de comparaison de périodes, d'objectifs configurables ou d'export. |
| Acteurs et permissions | 🟡 | Le cloisonnement entre boutiques est testé. En revanche, il n'existe qu'un rôle admin (« Administrators ») : pas de support, logistique, finance ni catalogue. Il n'y a pas non plus de journal horodaté des modifications sensibles (`myca_audit_log` prévu mais pas créé). |
| Parcours global de vente | 🟡 | *25/11 : la boutique commande désormais (M17, lot 2). Le paiement réel par carte attend le prestataire.* |
| Design system | 🟡 | Jetons, composants et états : faits. Le bleu de l'administration est absent. |
| Navigation et en-têtes | 🟡 | Menus par espace, fil d'Ariane et menu mobile : faits. La cloche de notifications et l'accès à l'aide sont absents. |
| Pied de page | ✅ | *Corrigé le 25/11 (lot de conformité 1) : 23 liens sur 23 répondent ; réseaux sociaux et moyens de paiement lus dans la configuration.* Avant : **13 liens sur 22 renvoyaient une 404** : CGV, CGU, confidentialité, cookies, FAQ, livraison, mentions légales, nos engagements, notre histoire, passeport, plan du site, qualité & matières, retours & échanges. Le cahier exige que « tous les liens du footer conduisent vers une route publiée ». Les réseaux sociaux ne sont pas cliquables (⏸ adresses à fournir). |

### Boutique B2C, commerce et paiements

| Fiche | État | Constat |
|---|---|---|
| Accueil commercial | ✅ | Page Builder éditable, collections, best-sellers, nouveautés. Seule manque la mesure des clics sur les appels à l'action. |
| Collections pays et Signature | 🟡 | Les capsules existent en catégories. Il manque la programmation et l'archivage, ainsi que la règle « passer un modèle en Signature » (déplacement manuel). |
| Catalogue et recherche | 🟡 | Recherche et filtres : faits. Manquent les filtres « disponible » et « nouveautés », la sélection multiple et le tri par popularité (⏸ V2–V4 de l'étape 2). |
| Fiche produit et variantes | 🟡 | Un SKU par variante, avec onglets. L'alerte de retour en stock n'est pas activée. La composition doit être validée par le fabricant (⏸). |
| Guide des tailles | ❌ | Absent : ni tableau de mesures, ni aide à la mesure. |
| Panier B2C | ✅ | Quantités, recalcul, seuil relais, rupture signalée. |
| Tunnel de commande | 🟡 | Il fonctionne grâce au repli Luma, mais n'est pas fidèle aux maquettes P07–P10 (Hyvä Checkout est payant ⏸). |
| Paiements B2C | 🟡 | *Lot 3 (25/11) : Stripe officiel en mode test — carte via Payment Element, webhooks signés et idempotents (R02 ✅), remboursements partiels et totaux en ligne, carte refusée et 3-D Secure testés. Restent les clés live, la limitation aux seuls moyens validés et le rapprochement bancaire.* |
| Tarifs et promotions | 🟡 | Les règles de panier natives couvrent codes, dates et quotas. Manquent l'historique des prix (prix de référence des réductions) et la validation admin des promotions importantes. |

### Espace client

| Fiche | État | Constat |
|---|---|---|
| Compte client B2C | 🟡 | Tableau de bord, commandes et factures : faits. Manquent la confirmation de l'e-mail, la suppression du compte par le client et la vérification de l'e-mail pour les changements sensibles. |
| Favoris et avis | 🟡 | Les favoris et les avis Magento natifs, modérés, existent. Manquent l'alerte quand un favori devient indisponible et la politique d'avis publiée. |
| Fidélité (page renforcée) | ✅ | Points en attente puis validés, retrait au prorata, journal en ajout seul, bons à usage unique, tests. Le barème reste à valider (⏸). La notification d'ajustement manque. |

### Produit et traçabilité

| Fiche | État | Constat |
|---|---|---|
| Passeport du vêtement | ✅ | Lots, QR, identifiant aléatoire, révocation, historique. |
| Certificat d'authenticité | ✅ | Délivré seulement sous condition, avec détection des consultations anormales. |
| Matières et entretien | 🟡 | Fiches techniques en production. Les symboles d'entretien et les données vérifiées par le fabricant manquent (⏸). |
| Production et fournisseurs | ✅ | MOQ, prévisionnel/engagé/reçu, jalons, coût rendu. |
| Contrôle qualité | ✅ | Checklist, tolérances, quarantaine qui bloque la vente. |

### Partenaires B2B

| Fiche | État | Constat |
|---|---|---|
| Espace partenaire | 🟡 | Tableau de bord, commandes, factures, profil, cloisonnement : faits. Les tarifs partenaires ne sont pas configurés (aucun prix de groupe ni grille). |
| Inscription et validation | ✅ | Candidature P17, contrôle SIRET/TVA, acceptation, refus motivé, suspension, e-mails. Seule manque la version des conditions B2B acceptées (seule la date est conservée). |
| Tarifs partenaires et grossistes | 🟡 | *Lot 2 : grille dropshipping, paliers de l’achat de stock, remise contractuelle par boutique, calcul côté serveur. Restent les prix par pays et la grille par référence.* |
| Dropshipping depuis le stock central | ✅ | *Lot 2 : commande avec destinataire final, e-mails à la boutique seule, bon de livraison sans prix au nom de la boutique.* |
| Achat de stock grossiste | 🟡 | *Lot 2 : minimum par modèle et couleur, paliers, livraison à la boutique, port offert au-delà d’un montant. Manquent les lots par taille et le conditionnement.* |
| Commande partenaire et destinataire (M17) | ✅ | *Lot 2 : 4 étapes, contrôles du stock, de la destination et du minimum, double validation impossible. L’annulation par la boutique reste à faire.* |
| Marges et rapports | ✅ | Ventes déclarées, marge estimée, export CSV. L'export PDF manque. |
| Boutique multi-utilisateur | 🟡 | Titulaire et collaborateur, invitation par e-mail, révocation : faits. Manquent les rôles distincts (gestionnaire, acheteur, SAV), la traçabilité des actions par utilisateur et la MFA. |

### Administration, stock et logistique

| Fiche | État | Constat |
|---|---|---|
| Administration centrale (M15) | ❌ | Il n'y a pas de tableau de bord unifié B2C/B2B avec la liste des tâches à traiter. |
| Gestion des commandes tous canaux | 🟡 | La grille Magento existe, mais le canal (B2C, dropship, stock) n'y est pas distingué. Les transitions d'état ne sont pas verrouillées. |
| Workflow logistique | ❌ | Il n'y a ni file de préparation, ni scan SKU, ni colis multiples, ni statuts recommandés. |
| Stock central « à la seconde » (page renforcée) | 🟡 | Les réservations MSI natives existent. Manquent le journal de mouvements avec UUID et clé d'idempotence, la diffusion temps réel (WebSocket/SSE), la mesure ≤ 1 s, les alertes et le **test de concurrence R01**. |
| Réassort et prévisions | ❌ | Il n'y a ni seuils, ni couverture, ni propositions de réassort. |
| Livraison Europe et seuil de 100 € (page renforcée) | 🟡 | La règle 99,99/100 € est faite et testée. Manquent la recherche des points relais, l'API transporteur et le blocage si l'API est indisponible (⏸ transporteur). |
| Suivi de livraison et notifications | ❌ | Il n'y a ni synchronisation avec le transporteur ni gestion des incidents de colis. |
| Retours B2C (page renforcée) | 🟡 | Portail, états et délai légal de 14 jours : faits. Le remboursement par le prestataire de paiement, l'étiquette de retour et la réservation automatique lors d'un échange manquent (⏸ prestataire et transporteur). |
| Retours B2B (page renforcée) | ✅ | Trois origines, bon vendeur, bon payeur. |

### Service, finance, international et conformité

| Fiche | État | Constat |
|---|---|---|
| Support et tickets | ❌ | Il n'y a pas de système de tickets. |
| FAQ et centre d'aide | 🟡 | *25/11 :* centre d'aide et FAQ par catégories, accordéons accessibles, recherche, renvoi vers le contact. Manquent la mesure des recherches sans réponse et le versionnage des réponses. |
| Facturation B2C | 🟡 | Factures et avoirs Magento : faits. L'identité légale du vendeur est à renseigner (⏸). |
| Facturation B2B et règlements | 🟡 | La boutique voit ses factures. Manquent les règlements, les impayés et le blocage selon le crédit. |
| Taxes, devises et international | 🟡 | Seule la TVA française est configurée. Les régimes OSS et IOSS sont à valider avec le comptable (⏸). |
| Multilingue (page renforcée) | ❌ | Il n'existe qu'une vue magasin, en français : ni anglais ni autre langue, pas de hreflang, pas d'URL par langue. Les e-mails Magento natifs ne sont pas traduits, faute de pack de langue. |
| RGPD | ❌ | Il n'y a ni registre, ni durées de conservation, ni export ou suppression à la demande, notamment pour les clients finaux des boutiques. |
| Cookies et consentement | 🟡 | *25/11 :* bandeau « Tout refuser / Tout accepter / Personnaliser », preuve enregistrée, retrait par « Gérer les cookies », traceurs bloqués avant accord, politique cookies publiée. La liste des outils réellement utilisés reste à compléter. |
| Sécurité des comptes et MFA | 🟡 | Hachage des mots de passe, verrouillage et CSRF natifs : faits. **La MFA admin est désactivée** (`Magento_TwoFactorAuth` est éteint). |
| Rôles et audit | ❌ | Il n'y a ni rôles admin séparés, ni journal d'audit centralisé, ni revue des accès. |

### Technique, marketing, contenus et livraison

| Fiche | État | Constat |
|---|---|---|
| Architecture applicative | 🟡 | Les modules sont découpés par domaine. Seul l'environnement local est documenté : test, préproduction et production manquent. |
| Modèle de données | 🟡 | Le schéma déclaratif existe. Manquent les tables de consentements, de tickets et de colis, ainsi que le journal de stock. |
| API et intégrations | ❌ | Aucune API REST MYCA (aucun `webapi.xml`) et aucun webhook. |
| Performance et accessibilité | 🟡 | Les pages sont contrôlées de 375 à 1600 px. Lighthouse n'a pas été mesuré. |
| SEO | 🟡 | *25/11 :* `sitemap.xml` généré chaque jour, `robots.txt` complété, pages privées en `noindex`. Le hreflang reste à faire (multilingue). |
| E-mails et communications | 🟡 | Les e-mails partenaires dédiés sont faits. Les autres e-mails Magento ne sont pas personnalisés ni traduits. L'envoi n'est pas configuré (⏸ SMTP). |
| Marketing et campagnes | 🟡 | Les codes promo et la newsletter natifs existent. Il n'y a pas de segments fondés sur les consentements. |
| Statistiques et marges | 🟡 | Les marges partenaires existent. Il n'y a pas de statistiques côté admin. |
| Import, export et migration | 🟡 | Les imports natifs de Magento (produits, stocks) existent. |
| Back-office contenu et CMS | 🟡 | Page Builder existe. Manquent le brouillon, la programmation, l'historique et l'approbation des pages juridiques. |
| Recette fonctionnelle | ❌ | Il n'y a ni référentiel d'exigences relié aux tests, ni tests d'intégration (83 tests unitaires répartis dans 17 fichiers), ni PV. |
| Livraison du code et documentation | 🟡 | Une documentation par étape existe. Manquent le guide d'installation, la documentation d'API, le manuel d'exploitation et la procédure de sauvegarde. |
| Roadmap et arbitrages | 🟡 | Le découpage en lots existe. Les décisions ouvertes sont dispersées dans les documents d'étape : il faut un registre unique. |

## 4. Maquettes (52)

| Espace | Faits | Partiels | Absents |
|---|---|---|---|
| Admin M01–M15 | M01, M10, M11 | M02–M09, M12–M14 : écrans Magento natifs, conformément à theme.md | M15 (tableau de bord) |
| Partenaire M16–M27 | M16, **M17**, M19, M21, M22, M23, M25 | M18 (catalogue sans tarif partenaire), M20 (pas de transporteur), M24 (consentements), M26 (rôles, MFA, intégrations) | M27 n'a pas été arbitrée face à M16. |
| Client M28–M40 | M28–M34, M36, M39, M40 | M35 (moyens de paiement : prestataire ⏸), M37 | M38 (Aide & FAQ) |
| Public M41–M52 | M41, M45, M46, M47, M50, M51, M52 | M43, M44, M48, M49 : pages publiées, textes juridiques et données réelles à compléter et à faire valider | — |

## 5. Recette obligatoire avant lancement (annexe F)

| Test | État |
|---|---|
| R01 Deux acheteurs sur la dernière pièce | ❌ Non testé |
| R02 Webhook et commande en double | ✅ Webhook Stripe rejoué deux fois : aucun doublon (lot 3) |
| R03 Réservation expirée puis paiement tardif | ❌ |
| R04 Affichage ≤ 1 s dans les trois espaces | ❌ Non mesuré, pas de diffusion temps réel |
| R05 Retour total B2C | 🟡 Avoir, quarantaine et points testés ; remboursement par le prestataire absent |
| R06 Retour partiel après dépense des points | ✅ Testé (solde négatif compensé) |
| R07 Dropshipping : remboursement par la boutique | ✅ Testé |
| R08 Achat en magasin : pas de remboursement MYCA | ✅ Testé |
| R09 / R10 Seuil 99,99 € / 100 € | ✅ Tests unitaires et panier réel |
| R11 Pays non couvert, relais complet, API indisponible | 🟡 Pays non couvert : fait ; relais et API : absents |
| R12 Toutes les langues publiées, hreflang | ❌ Une seule langue |

**Go / no-go** : la mise en production reste bloquée tant que manquent :
- le paiement réel ;
- les pages juridiques et le bandeau cookies (risque d'« erreur légale ») ;
- le test de concurrence sur le stock.

## 6. Ce qui dépend de vous (⏸)

- **Prestataire de paiement et transporteurs** (relais et domicile), avec les **destinations desservies**.
- **Textes juridiques et données réelles** :
  - textes : CGV B2C et B2B, CGU, mentions légales, confidentialité, cookies, politique de retour (14 ou 30 jours) ;
  - données : raison sociale, SIRET, hébergeur.
- **Tarifs partenaires** (dropshipping et grossiste), **minimums de commande** et **barème de fidélité**.
- **Données fabricant** (compositions, entretien), **photos HD**, **coordonnées et horaires** du service client.
- **Langues à livrer** au 31/12 (le cahier demande de les lister dans le contrat).
- **Licence Hyvä Checkout**, si le tunnel doit suivre les maquettes P07–P10.
- **Figma responsive** : à produire, ou à exclure par avenant.
- **Arbitrage contractuel** : périmètre au regard du forfait de 1 500 € et de l'échéance (avenant signé).

## 7. Priorités proposées pour les 5 semaines restantes

1. **Bloquants de mise en ligne** :
   - pages publiques et juridiques (M41–M52), avec les 13 liens du pied de page ;
   - bandeau cookies (« Tout refuser » aussi visible que « Tout accepter ») ;
   - sitemap ;
   - page 404 MYCA.
2. **Commande partenaire M17** (dropshipping et achat de stock), avec les tarifs partenaires et le bon de livraison sans prix.
3. **Paiement** : brancher le prestataire officiel, avec webhooks, remboursements et tests R02/R03.
4. **Sécurité et rôles** :
   - MFA admin ;
   - rôles admin (support, logistique, finance, catalogue) ;
   - journal d'audit ;
   - rôles de boutique.
5. **RGPD et compte client** : confirmation de l'e-mail, suppression du compte, durées de conservation, export et suppression des clients des boutiques (lot A proposé).
6. **Multilingue** : au minimum FR + EN (vue magasin, hreflang, e-mails), si EN figure dans les langues contractuelles.
7. **Recette** : test de concurrence R01, référentiel d'exigences, guide d'installation, procédure de sauvegarde et de restauration, PV.

Hors de ces 5 semaines, sauf avenant :
- tableau de bord admin M15 et statistiques ;
- workflow logistique ;
- réassort ;
- support et tickets ;
- API ;
- diffusion du stock en temps réel ≤ 1 s.
