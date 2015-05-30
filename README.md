# csv2joom

Importieren/Erstellen von Kategorien und Bildern in die JoomGallery basierend auf csv-Dateien 


Die JoomGallery bietet eine Migrations-Manager (siehe http://www.joomgallery.net/dokumentation/das-backend-die-schaltzentrale/weitere-manager/der-migrations-manager.html )
Hierzu lassen sich beriets diverse Migrations-Skripte herunterladen (siehe http://www.joomgallery.net/downloads/joomgallery-fuer-joomla-3/addons/migrationsskripte.html ), um Daten aus älteren JoomGallery-Versionen oder anderen Bildergalerien zu migrieren. 

Dieses Skript soll nun dazu dienen, die JoomGallery mittels vom Nutzer bereitgestellter csv-Dateien mit Kategorien und Bildern zu füllen.
(Ich hatte eine eigene Datenbank mit einer größeren Anzahl Bild- und Kategoriedaten (>5000 Bilder; >1000 Kategorien), konnte und wollte die also unmöglich per Hand in der JoomGallery erstellen. Deshalb ist das csv2joom-Migrationsskript entstanden...)


Das Skript liegt nun in einer ersten Version vor. 
Getestet habe ich mit folgenden Versionen:
- Joomla 3.4.1
- JoomGallery 3.2.1
- erfolgreich importiert/erstellt wurden gut 1000 Kategorien mit über 5600 Bildern

## Folgende Dinge sollten funktionieren:

### Installation des Skriptes
- auf der [github Projektseite](https://github.com/fruwolt/csv2joom-dev) kann ein zip-File heruntergeladen werden: [Download Skript Direktlink](https://github.com/fruwolt/csv2joom-dev/archive/master.zip)
- es lässt sich über den normalen Installationsvorgang von Joomla installieren
- das Skript integriert sich in den Migrations-Manager der JoomGallery
- bei der Installation werden Beispiele für csv-Dateien (Kategorien und Bilder) inkl. Beispiel-Bilder im Ordner [Joomla-Pfad]/tmp/csv2joom/ erstellt

### Import von Kategorien
- eine csv-Datei (Komma-separiert, Werte in doppelten Anführungszeichen; siehe auch Beispiel-csv) wird über das JoomGallery-Backend (Migrationsmanager) hochgeladen und abgearbeitet

### Import von Bildern
- eine csv-Datei (Komma-separiert, Werte in doppelten Anführungszeichen; siehe auch Beispiel-csv) wird über das JoomGallery-Backend (Migrationsmanager) hochgeladen und abgearbeitet
- die Bilder selbst müssen dazu z.B. mittels FTP in einen speziellen Ordner kopiert werden
- das Skript kopiert dann mit Hilfe von durch den Migrations-Manager bereitgestellten Funktionen die Bilder in die JoomGallery-Ordnerstruktur und erstellt die Vorschaubilder
- die zu importierenden Bilder / Bild-Dateien müssen unterhalb des Ordners [Joomla-Pfad]/tmp/csv2joom/ abgelegt werden
- ausgehend von diesem Basis-Pfad muss für die Bilder dann in der csv-Datei der entsprechende relative Pfad angegeben werden
- Beispiel: für das Bild bild1.jpg, gespeichert in [Joomla-Pfad]/tmp/csv2joom/images/category1/bild1.jpg lautet der anzugebende Pfad + Dateiname: images/category1/bild1.jpg
- die Bilder werden beim Import von dort in die JoomGallery-eigenen Ordner kopiert 

## Wissenswertes vorab:
a) 
- ich empfehle, den Import erst mal auf einem Testsystem auszuprobieren und/oder MINDESTENS ein VOLLSTÄNDIGES Backup ALLER Daten der Website vorzunehmen (Dateien und Datenbank)!

b)
- die Kategorien bekommen im Zuge des Imports neue Kategorie-IDs zugewiesen
- um die Zuordnung der Bilder zu den dann geänderten Kategorie-IDs kümmert sich das Skript - siehe auch d)

c)
- Beispiel-Daten siehe Ordner [Joomla-Pfad]/tmp/csv2joom/
- die Reihenfolge der Kategorien in der csv-Datei ist wichtig/relevant
- vorerst müssen die Kategorien gemäß ihrer "Enthaltensein-Beziehung" beginnend mit der/den übergeordneten Kategorien (Wurzel-Kategorie) aufgelistet werden 
- d.h. Kategorien der obersten Ebene gefolgt von Kategorien der Ebene 1, Eben 2 usw. (Baumstruktur Wurzel Richtung Blattknoten)
- Kategorien sind in der csv-Datei immer mit ihrer eigenen ID gefolgt von der ID der übergeordneten Kategorie (parent-ID) angegeben
- die Kategorien der obersten Ebene (Wurzel) bekommen für die ID der übergeordneten ID eine 0
- d.h. Kategorien mit parent-ID 0 werden als Haupt-Kategorie angelegt (unterhalb der JoomGallery-eigenen Wurzel/Basis-Kategorie)

d)
- der Basis-Pfad für csv2joom lautet [Joomla-Pfad]/tmp/csv2joom/
- hier werden beim Import folgende Dateien angelegt:
-- csv2joom.log (Log-Datei)
-- csv2joom_already_stored_cats.txt (Hilfs-Datei für Import der Kategorien; enthält Liste mit IDs der bereits importierten Kategorien)
-- csv2joom_already_stored_imgs.txt (Hilfs-Datei für Import der Bilder; enthält Liste mit IDs der bereits importierten Bilder)
-- csv2joom_catmapping.csv (Zuordnung alter/originaler Kategorie-IDs auf neu vergebene Kategorie-IDs 
-- dieses Zuordnung ist wichtig für den Bild-Import, damit diese den richtigen Kategorien zugeordnet werden können
-- datafileCat.csv (die csv-Datei mit Kategorie-Daten nach Upload) 
-- datafileImg.csv (die csv-Datei mit Bild-Daten nach Upload)

--> vor einem komplett neuen Import sollten all diese Dateien verschoben/gelöscht/umbenannt werden / der Basis-Pfad leer sein
--> vor einem Bild-Import muss die Datei csv2joom_catmapping.csv vorhanden sein
--> Bilder ohne ermittelbare Kategorie-ID werden per default der Kategorie mit ID 1 zugeordnet (entspricht der Wurzel-Kategorie der JoomGallery)


## Was ist zu tun:

1. 
nach Installation der JoomGallery (falls noch nicht vorhanden) das csv2joom-Skript installieren

2. 
für den Upload der csv-Dateien muss eine Datei der JoomGallery angepasst werden
-die Datei dient der Darstellung von Formularen (im Mogrationsmanager der JoomGallery), unterstützt zur Zeit aber nicht den Upload von Dateien
-hierzu also bitte die Datei [Joomla-Pfad]/administrator/components/com_joomgallery/layouts/joomgallery/migration/form.php bearbeiten und folgende Zeile
`<form action="<?php echo $displayData->url; ?>" method="post" class="form-horizontal form-validate">`
ersetzen mit
`<form action="<?php echo $displayData->url; ?>" method="post" class="form-horizontal form-validate" enctype="multipart/form-data">`
-mit dem `enctype="multipart/form-data"` unterstützt das Formular dann den Datei-Upload
(entweder unterstützen spätere JoomGallery-Versionen das ja vielleicht ohne Änderung oder alternativ werde ich das Skript anpassen, dass die Dateien auch  auf den Server kopiert werden können (z.B. per FTP) und man gibt dann im Formular nur noch den Pfad zur Datei an..)

3. 
Erstellen eigener csv-Dateien mit den eigenen Kategorien/Bilddaten oder Nutzen der Beispiel-Dateien
-Formatierung der csv-Dateien siehe Beispiel-Dateien

4.
-Import der Kategorie-Daten, dazu:
-Migrationsmanager der JoomGallery öffnen und csv2joom-Formular suchen "Csv2joom - Import aus CSV-Dateien in JoomGallery"
-csv-Datei für Kategorien hochladen mit Button "Schritt 1: CSV-Datei Kategorien" + Klick auf "Checken"-Button
-wenn alles ok ist, erscheint: "Die Migration kann gestartet werden."; dann auf Button "Starten" klicken
-Import-Vorgang läuft 

5.
-Import der Bilder, dazu:
-Migrationsmanager der JoomGallery öffnen und csv2joom-Formular suchen "Csv2joom - Import aus CSV-Dateien in JoomGallery"
-csv-Datei für Kategorien hochladen mit Button "Schritt 2: CSV-Datei Bilder" + Klick auf "Checken"-Button
-wenn alles ok ist, erscheint: "Die Migration kann gestartet werden."; dann auf Button "Starten" klicken
-Import-Vorgang läuft
