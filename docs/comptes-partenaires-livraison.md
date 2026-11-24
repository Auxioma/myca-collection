# MYCA — Comptes partenaires B2B : candidature P17 et gestion M01 (`Myca_Partner`)

Livré le 24/11/2026. **Point de validation : STOP.**

Il existe désormais deux façons de créer un compte partenaire B2B :

1. **La boutique candidate** sur la page publique `/devenir-partenaire` (P17). MYCA examine ensuite la candidature dans l'administration.
2. **MYCA crée la boutique directement** dans l'administration (M01), par exemple pour un partenaire déjà connu.

Dans les deux cas, la boutique reçoit un code `PART-NNNNNN`. Son titulaire passe dans le groupe client partenaire (tarifs B2B) et reçoit l'e-mail Magento qui l'invite à définir son mot de passe. Il accède ensuite à l'espace partenaire `/partenaire`.

## 1. Page publique P17 « Devenir partenaire »

- **Adresse** : `/devenir-partenaire`. Un lien « Devenir partenaire » est ajouté dans le pied de page, colonne Aide & Services.
- **Contenu de la page** :
  - l'accroche « Vendez MYCA dans votre boutique », avec l'image de la maquette ;
  - les trois cartes Dropshipping, Achat de stock et Votre espace partenaire ;
  - le formulaire de candidature.
- **Champs du formulaire** :
  - obligatoires : raison sociale, SIRET, pays, nom du contact, e-mail professionnel, téléphone, mode souhaité (dropshipping, stock ou les deux) ;
  - facultatifs : n° de TVA, site web, adresse, code postal, ville, volume annuel estimé, présentation.
- **Consentements obligatoires** : acceptation des conditions de vente B2B (la date est enregistrée) et accord pour être recontacté.
- **Contrôles** :
  - SIRET : 14 chiffres et clé de Luhn, avec la règle propre à La Poste ;
  - TVA française : la clé est vérifiée et le SIREN doit être celui du SIRET ;
  - TVA d'un autre pays européen : le format est vérifié ;
  - téléphone et e-mail.
- **En cas d'erreur**, le message s'affiche et la saisie est conservée.
- **Une seule candidature en attente** est acceptée par SIRET ou par e-mail. Une société déjà partenaire est refusée.
- **Anti-spam** : un champ piège, invisible pour les visiteurs.

## 2. Administration : Clients › Candidatures partenaires

- **Grille** : date, société, SIRET, contact, e-mail, pays, mode souhaité, statut (À examiner, Acceptée, Refusée), avec filtres et recherche.
- **Fiche** : toutes les informations déclarées et les consentements.
- **Accepter** :
  - crée la boutique, active ;
  - crée le compte du contact, ou reprend un compte client existant avec le même e-mail ;
  - fait de ce compte le **titulaire** ;
  - ouvre ensuite la fiche partenaire.
- **Refuser** : le motif est obligatoire et enregistré.

## 3. Administration : Clients › Partenaires B2B (M01)

- **Grille** : code, société, SIRET, e-mail, ville, modes, statut, date. Le bouton **Nouveau partenaire** permet la création directe.
- **Fiche** :
  - les indicateurs (commandes sur 30 jours, achats HT sur 30 jours, nombre total de commandes) ;
  - les informations de la boutique, modifiables ;
  - le statut ;
  - les comptes de la boutique ;
  - les dernières commandes.
- **Statut** :
  - **Suspendre** demande un motif. Une boutique suspendue n'a plus accès à l'espace partenaire, mais ses données sont conservées.
  - **Activer** rétablit l'accès.
- **Comptes** :
  - **Ajouter un compte** (titulaire ou collaborateur) crée le client s'il n'existe pas. Un compte déjà rattaché à une autre boutique est refusé.
  - **Retirer** un compte le remet dans le groupe client par défaut.
  - La boutique garde toujours **au moins un titulaire**.
- **Droits (ACL)** : consulter, modifier, changer le statut, traiter les candidatures. Chaque droit peut être attribué séparément aux rôles administrateurs.

## 4. Vérifications

- **Tests unitaires** : 81 réussis, dont le validateur (SIRET, TVA, téléphone, modes, nom).
- **PHPStan niveau 8** : 0 erreur.
- **PHPCS Magento2** : 0 erreur.
- **Parcours testés dans le navigateur** :
  - candidature avec un SIRET invalide (erreur, saisie conservée) ;
  - candidature valide ;
  - doublon refusé ;
  - acceptation : la boutique PART-000160 est créée, sa titulaire est dans le groupe partenaire ;
  - refus avec motif ;
  - suspension puis réactivation ;
  - ajout puis retrait d'un collaborateur, qui revient dans le groupe par défaut ;
  - retrait du dernier titulaire, refusé ;
  - création directe : SIRET invalide refusé, SIRET en double refusé, puis boutique PART-000161 créée ;
  - connexion de la nouvelle partenaire et accès à `/partenaire`.
- **Captures** : `comptes-partenaires/` (P17 à 375 et 1280 px, fiche candidature, fiche partenaire). La page ne défile pas horizontalement à 375, 768, 1280 et 1600 px.

## 5. Complément du 24/11/2026 : e-mails dédiés, alerte MYCA, conditions B2B

### E-mails dédiés (cinq modèles en français)

| E-mail | Destinataire | Envoyé quand |
|---|---|---|
| Candidature reçue | la boutique | elle dépose sa candidature |
| Nouvelle candidature | **l’équipe MYCA** | à chaque candidature, avec un bouton « Examiner la candidature » ; répondre à l’e-mail écrit directement au contact de la boutique |
| Candidature acceptée | la boutique | MYCA accepte la candidature ; contient le code partenaire et le lien **« Définir mon mot de passe »** |
| Candidature refusée | la boutique | MYCA refuse la candidature ; contient le motif saisi |
| Invitation | le compte ajouté | MYCA crée une boutique ou ajoute un compte depuis la fiche partenaire |

- **Un seul e-mail par événement.** L’e-mail d’acceptation porte lui-même le lien pour définir le mot de passe, donc l’e-mail Magento « compte créé » n’est plus envoyé en plus. Si le compte client existait déjà, l’e-mail propose « Me connecter » à la place.
- **Modifier un e-mail** :
  1. dans **Marketing › Modèles d’e-mails**, cliquez sur « Ajouter un modèle » ;
  2. chargez un modèle « MYCA – … » et modifiez-le ;
  3. choisissez-le ensuite dans la configuration (voir plus bas).
- **Pied de page** : tous les e-mails du site affichent désormais « MYCA – L’élégance sportive » au lieu de « Thank you, Main Website Store! ».
- **Sécurité** : les valeurs saisies par les candidats sont toujours échappées dans les e-mails. Un e-mail qui ne part pas est journalisé et ne bloque jamais l’action.

### Alerte à MYCA pour chaque nouvelle candidature

- **Adresse** : réglable dans **Magasins › Configuration › MYCA › Espace partenaire › Candidatures et e-mails**. Valeur par défaut : `partenaires@myca.fr`. Plusieurs adresses sont possibles, séparées par des virgules.
- **Même écran** : l’expéditeur et le modèle utilisé pour chacun des cinq e-mails.
- **Lien « Examiner la candidature »** : il ouvre directement la candidature après connexion à l’administration. Cette page de consultation est la seule dispensée de clé secrète d’URL, car la clé ne peut pas figurer dans un e-mail. La connexion et le droit « candidatures » restent exigés, et les boutons Accepter et Refuser gardent leur protection.

### Page « Conditions de vente professionnelles »

- **Page CMS** `/conditions-vente-professionnelles`, modifiable dans **Contenu › Pages** avec Page Builder.
- **Lien** : la case « J’accepte les conditions de vente professionnelles (B2B) » du formulaire ouvre cette page dans un nouvel onglet. Une autre page peut être choisie dans la configuration (champ « Page des conditions de vente professionnelles »).
- **Contenu** : un **modèle de 13 articles** (objet, compte, modèles de vente, commandes, prix, paiement avec les mentions légales de retard, livraison, réserve de propriété, retours, image de marque, responsabilité, données, durée et litiges). Les valeurs propres à MYCA sont entre crochets **[à compléter]**. La mention « Modèle à faire valider par votre conseil juridique » est à retirer après validation.
- **Mise en forme** : nouvelle classe `myca-pb-legal` (titres, paragraphes aérés). Elle se pose dans le champ « Classes CSS » d’un élément Texte et sert aussi pour les CGV, les mentions légales, etc.

### Pays

Tous les pays restent acceptés dans le formulaire, comme convenu.

### Vérifications

- **Tests unitaires** : 83 réussis.
- **PHPStan niveau 8** : 0 erreur.
- **PHPCS** : 0 erreur.
- **Les cinq e-mails générés** (l’envoi est désactivé en local) : textes, liens et cas « compte existant » ou « compte créé » corrects. Un nom de société contenant du HTML reste bien échappé.
- **Parcours testé dans le navigateur** :
  - candidature, puis ouverture par le lien de l’e-mail admin ;
  - acceptation : la boutique PART-000162 est créée, son compte sans mot de passe avec un jeton ;
  - invitation d’un collaborateur ;
  - le lien « Définir mon mot de passe » ouvre bien le formulaire de Magento ;
  - écran de configuration ;
  - page des conditions sans débordement à 375 et 1280 px.
- **Captures** : `comptes-partenaires/conditions-b2b-1280.png`, `comptes-partenaires/configuration-emails.png`.

## 6. Points à valider

1. **Durée de validité du lien « Définir mon mot de passe »** : 2 heures, le réglage Magento par défaut (Magasins › Configuration › Clients › Configuration client › Mot de passe). C’est court pour une invitation, mais l’allonger vaut aussi pour les liens « mot de passe oublié ». Proposition : 24 heures.
2. **Envoi réel des e-mails** : il est désactivé dans cet environnement local. Le serveur de production doit avoir un envoi SMTP configuré, et l’expéditeur « Contact général » doit porter une adresse MYCA.
3. **Texte des conditions B2B** : compléter les valeurs entre crochets et faire valider le texte par votre conseil juridique.
