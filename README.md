# JT - ALDEF
### JoomTools - Automatic local download external files

#### Beschreibung / Description 
<details>
  <summary>Deutsch/German</summary>

#### Deutsche Beschreibung
<p>Dieses Plugin lädt extern geladene Dateien oder Schriften in das lokale Dateisystem herunter und stellt sie von der lokalen Domäne aus zur Verfügung.<br /><strong>Zur Zeit werden nur Google-Schriften verarbeitet.</strong> Weitere Dienste wie CDNs oder Fontfabriken, auch externe Skripte oder Bilder, sind für die Zukunft geplant.</p><p><strong>Zwei Möglichkeiten der Einbindung werden unterstützt:</strong></p><ol><li><code>&lt;link href="https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="(lazy-)stylesheet" /&gt;</code><br /><br /></li><li><ul style="list-style-type:square"><li><code>@import "https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap";</code></li><li><code>@import url("https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap");</code></li><li><code>@import url(https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap);</code></li></ul></li></ol><p>Methode 1 wird im Seitenkopf <sup>a)</sup> erkannt, Methode 2 wird sowohl in den eingebundenen CSS-Dateien der ersten Ebene im Seitenkopf als auch in den Style-Tags im Seitenkopf und im Seiteninhalt <sup>b)</sup> erkannt.</p><p><strong>Google bietet auch zwei Varianten an, die mir derzeit bekannt sind, um die Schriften abzurufen:</strong></p><ol><li><code>https://fonts.googleapis.com/css?family=PT+Sans+Caption:400,700|PT+Sans:400,400i,700,700i</code></li><li><code>https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700</code></li></ol><p>Beide werden gefunden und ersetzt.</p>

### Allgemeiner Hinweis
<ul><li>Es empfiehlt sich das Plugin als letztes in der Reihenfolge anzuordnen.</li><li>Nach Änderungen an den CSS-Dateien, sollte der Index zurückgesetzt werden.</li></ul>
<p>___<br /><strong>Legende:</strong><br /><sup>a) Seitenkopf ist der HTML-Bereich zwischen <code>&lt;head&gt;</code> und <code>&lt;/head&gt;</code></sup><br /><sup>b) Seiteninhalt ist der HTML-Bereich zwischen <code>&lt;body&gt;</code> und <code>&lt;/body&gt;</code></sup></p>
</details>

<details>
  <summary>Englisch/English</summary>

#### English description
<p>This Plugin downloads external loaded files or fonts to the local file System and serves them from the local Domain.<br /><strong>Currently only Google fonts are handled.</strong> Other services like CDNs or font factories, also external scripts or images, are planed for the future.</p><p><strong>Two ways of embedding are supported:</strong></p><ol><li><code>&lt;link href="https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="(lazy-)stylesheet" /&gt;</code><br /><br /></li><li><ul style="list-style-type:square"><li><code>@import "https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap";</code></li><li><code>@import url("https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap");</code></li><li><code>@import url(https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap);</code></li></ul></li></ol><p>Method 1 is recognized in the page header, method 2 is recognized both within the included CSS files of the first level in the page header and in the style tags in the page header and in the page body.</p><p><strong>Google also offers two variants, which are currently known to me, to retrieve the fonts:</strong></p><ol><li><code>https://fonts.googleapis.com/css?family=PT+Sans+Caption:400,700|PT+Sans:400,400i,700,700i</code></li><li><code>https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700</code></li></ol><p>Both are found and replaced.</p>

### General note
<ul><li>It is recommended to place the plugin as last in the order.</li><li>After making changes to the CSS files, the index should be reset.</li></ul>
</details>
