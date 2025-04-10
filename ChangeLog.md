# Change Log
All notable changes to this project will be documented in this file

## UNRELEASED



## Release 1.6
- FIX : dDA024860 supplementary work revamp in pdf situation pdf_sponge_btp - *08/04/2025* - 1.6.4  
- FIX : Compat v21 - **17/12/2024** - 1.6.3
- FIX : No depends on modules - *04/09/2024* - 1.6.2
- FIX : DA025402 - L'encadré des objets liés dans le pdf sponge btp a été retiré - *30/08/2024* - 1.6.1
- FIX : Compat v20
  Changed Dolibarr compatibility range to 16 min - 20 max - *04/08/2024* - 1.6.0

## Release 1.5
- FIX : sponge_btp: calcul de la retenue de garantie actuelle - *29/04/2024* - 1.5.2
- FIX : Warnings lors de la génération du pdf Sponge - *08/04/2024* - 1.5.1
- NEW : Add TechATM and rebuild About page + Update US translation - *08/01/2024* - 1.5.0
- NEW : Dolibarr compatibility V19 - *04/12/2023* - 1.4.0 
  	Changed Dolibarr compatibility range to 15 min - 19 max  
  	Changed PHP compatibility range to 7.0 min - 8.2 max

## Release 1.3
- FIX : sponge_btp: sur première facture de situation: nouveau cumul et situation actuelle rendus identiques - *24/11/2023* - 1.3.9
- FIX : setNewPage bad type hint - *22/06/2023* - 1.3.8
- FIX : warning foreach in sponge - *22/06/2023* - 1.3.7
- FIX : mise en forme du tableau des totaux bas de page facture de situation crabe_btp  - *31/05/2023* - 1.3.6
- FIX : mise en forme du tableau des totaux bas de page facture de situation sponge_btp - *17/05/2023* - 1.3.5  

- FIX : warning php 8 pdf btp *26/01/2023* - 1.3.4
- FIX : dédoublement de la ref commande dans note_public lors de la génération des pdf 'crabe_btp' & 'sponge_btp' *05/10/2022* - 1.3.3
- FIX : fatal error dans le modèle sponge_btp dû à une fuite de mémoire - *04/08/2022* - 1.3.2
  - suppression de caches inutiles
  - optimisation des calculs en boucle
- FIX : taille du tableau préliminaire variable - *29/06/2022* - 1.3.1
- NEW : Intégration bénéfice prévisionnel sur vue d'ensemble chantier - *31/05/2022* - 1.3.0  
  *(récupération de 8.0_btp)* 

## Release 1.2

- FIX : Compatibilité v16 : this->family *09/06/2022* - 1.2.1
- NEW : Nouvelle configuration *14/01/2022* - 1.2.0  
  Ajoute une configuration pour que les marges présentes dans le tableau des cmd, propal, factures
  tiennent compte de la part de produits / services disponibles dans les ouvrages et sous ouvrages des lignes

## Release 1.1

- FIX : Changement de la valeur de retour par défault dans le cas d'une retenue de garantie à 0% qui provoquait une incohérence entre les deux lignes TTC sur pdf_sponge_btp - 19/11/2021 - *1.1.9*
- FIX : Traduction sur facture de situation *26/06/2021* - 1.1.8
- FIX : module descriptor (v14 compatibility) *30/06/2021* - 1.1.7
- FIX : Remove useless dependency for workstation *29/06/2021* - 1.1.6
- FIX : V13 GETPOST compatibility *08/03/2021*
