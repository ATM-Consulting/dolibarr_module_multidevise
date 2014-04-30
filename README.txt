+--------------------+
| 	Multidevise 	 |
+--------------------+

Module développé par la société ATM Consulting.  http://www.atm-consulting.fr
Version : Dolibarr 3.5
Documentation utilisateur : http://wiki.atm-consulting.fr/index.php/Multidevise/Documentation_utilisateur

+------------------------------------+
| 	Installation  et Configuration	 |
+------------------------------------+

1 - Copiez les répertoires multidevise et abricot dans le répertoire htdocs de votre dolibarr.
2 - Copiez et remplacer dans le répertoire htdocs de votre Dolibarr les fichiers présent dans le répertoire htdocs du module Multidevise.
3 - Activez le module Multidevise (l'activation prends un certain temps car le module synchronise les taux de convertion depuis une API en ligne)
4 - Le module est actif et fonctionnel, vous pouvez dès à présent l'utiliser.

NB : par défaut le module définit le paramêtre BUY_PRICE_IN_CURRENCY comme étant inactif.
Celui-ci définit que les prix fournisseur sont considérés comme étant dans la devise associé au fournisseur (ex: Fournisseur X en Dollars = prix fournisseur tacitement concidéré comme renseigné en Dollars)
Pour activer ce paramêtre, renseigné dans Accueil -> Configuration -> Divers une variable avec pour nom BUY_PRICE_IN_CURRENCY et pour valeur 1 (0 pour désactiver).

NB+ : le module abricot ne requiert aucune activation pour être fonctionnel et n'impacte pas le fonctionnement de Dolibarr.
Il contient un ensemble de programmes nécessaire au bon fonctionnement de Multidevise, sa présence est obligatoire.