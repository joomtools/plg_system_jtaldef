# JT - ALDEF
### JoomTools - Automatic local download external files

This Plugin downloads external loaded files or fonts to the local file System and serves them from the local Domain.  
**Actualy we handle only Google Fonts.** Other services like CDNs or font factories, also external scripts or images, are planed for the future.

**Two ways of embedding are supported:**
1. `<link href="https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet" />`  

2. - `@import "https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap";`
   - `@import url("https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap");`
   - `@import url(https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap);`

Method 1 is recognized in the page header, method 2 is recognized both within the included CSS files of the first level (page header) and in the style tags (page header and page body).


**Google also offers two variants, which are currently known to me, to retrieve the fonts:**
1. `https://fonts.googleapis.com/css?family=PT+Sans+Caption:400,700|PT+Sans:400,400i,700,700i`
2. `https://fonts.googleapis.com/css2?family=PT+Sans+Caption:wght@400;700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700`

Both are found and replaced.
