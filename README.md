# Like Winisis Search for ABCD +4

The **Like Winisis Search** plugin is an official extension for the **ABCD System (version 4.1.0 or higher)** that restores the agility and classic advanced search syntax of Winisis directly within the modern Cataloging module (*dataentry*) interface.

---

## 🚀 Key Features

- **Contextual Sidebar Panel:** Smoothly slides out from the left side of the screen when clicking the dedicated toolbar icon.
- **Intelligent Field Content Preview (*The Clever Twist*):** In addition to displaying the MFN, the sidebar list displays the contents of the exact searched field, making it easy to identify records before opening them.
- **Instant Record Loading:** Clicking any item in the sidebar list immediately loads the full record into the main panel on the right using the standard cataloging display format.
- **Optimized WXIS Middleware:** Translates legacy syntax directly into optimized parameters for the CISIS/WXIS engine, ensuring stellar performance even on large databases.
- **Full Internationalization:** Fully localized for English, Portuguese, Spanish, and French via ABCD's `LanguageManager`.

---

## 📝 Supported Syntax

The plugin natively supports the following legacy operators directly in the search input box:

1. **Field Presence (`?p`)**
   - *Syntax:* `?p(vXX)`
   - *Example:* `?p(v100)` (locates all records containing field 100 and displays its content in the sidebar list).

2. **Relational MFN Filter (`?mfn`)**
   - *Syntax:* `?mfn > XX` or `?mfn < XX`
   - *Example:* `?mfn>3` (retrieves records with an MFN greater than 3).

3. **String / Substring Search (`? vXX:"text"`)**
   - *Syntax:* `? vXX:"text"` or bounded by an MFN range `? *start,end vXX:"text"`
   - *Example:* `? v0:"Petrarca"` or `? *1,100 v245:"History"`.

---

## 🛠️ System Requirements

- **ABCD System:** Version **4.1.0** or higher (requires core structural hooks `dataentry_toolbar_search` and `dataentry_footer`).
- **PHP:** Version **8.3+**.
- **Architecture:** Built upon the modern plugin ecosystem using `PluginBridge`.

---

## 📦 Manual Installation

1. Download the compressed release package (`.zip`) from the repository Releases.
2. Extract the `like-winisis` folder into your ABCD content plugins directory:
   `/htdocs/content/plugins/like-winisis/`
3. Log into your ABCD administrative panel and navigate to **Configuration > Plugin Manager** (`plugins_manager.php`).
4. Locate **Like Winisis Search** and click **Activate**.