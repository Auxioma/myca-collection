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

## 5. Points à valider

1. **E-mails** : la boutique ne reçoit pour l'instant aucun e-mail quand sa candidature est reçue, acceptée ou refusée. Seul part l'e-mail Magento de création de compte. Faut-il des modèles d'e-mail dédiés ?
2. **Notification de l'équipe MYCA** à chaque nouvelle candidature : à quelle adresse ?
3. **Conditions de vente B2B** : la case renvoie à des conditions qui n'ont pas encore de page. Il faut fournir le texte, ou l'adresse de la page.
4. **Pays acceptés** : la France est proposée en premier, mais tous les pays sont acceptés. Faut-il se limiter à l'Union européenne ?
