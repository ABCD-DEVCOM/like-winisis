# Like Winisis Search for ABCD +4

The **Like Winisis Search** plugin is an official extension for the **ABCD System (version 4.1.0 or higher)** that restores the agility and classic advanced search syntax of Winisis directly within the modern Cataloging module (*dataentry*) interface.

---

## 🚀 Key Features

- **Contextual Sidebar Panel:** Smoothly slides out from the left side of the screen when clicking the dedicated toolbar icon (`fa-terminal`), dynamically adjusting the main cataloging frame so records are never obscured.
- **Intelligent Field Content Preview (*The Clever Twist*):** In addition to displaying the MFN, the sidebar list displays the actual contents of the searched field(s), making it easy to identify records before opening them.
- **Active Session Integration (`Selected Records`):** Automatically synchronizes retrieved MFNs with ABCD's native `RegistrosSeleccionados` (`selected_records`) navigation mode. Users can seamlessly navigate through results using ABCD's native toolbar buttons (*First, Previous, Next, Last*) or apply batch operations (*Print, Global Change, Export*) to the retrieved set.
- **Boolean Combinations & Full-Record Search:** Supports combining multiple legacy expressions using boolean operators (`AND`, `OR`, `NOT`) and parentheses, as well as full-record string searches (`v0`).
- **Session History & Saved Expressions:** Tracks all queries executed during the active session (`#1`, `#2`, etc.) and allows bookmarking frequently used expressions for future reuse.
- **Optimized WXIS Middleware:** Translates legacy syntax directly into safe, optimized CISIS Formatting Language (PFT) conditions executed via `wxis_llamar.php`.
- **Full Internationalization:** Fully localized for English, Portuguese, Spanish, and French via ABCD's `LanguageManager`.

---

## 📖 How to Use

1. **Open the Sidebar:** In the Cataloging (*dataentry*) module toolbar, click the **Terminal icon** (`>_`) located right next to the quick-search input box.
2. **Enter an Expression:** Type your legacy Winisis search expression into the input box (e.g., `?p(v100)` or `?v10:"Maria" AND v20:"Brasil"`).
3. **Execute the Search:** Press `Enter` or click the **Play** button (`fa-play`).
   - The first matching record will automatically load in the main cataloging panel on the right, and ABCD's navigation mode will switch to **Selected Records** (`T = total`).
   - Click any item in the sidebar list to inspect that specific MFN in the main panel.
4. **Save Favorite Expressions:** Click the **Bookmark** button (`fa-bookmark`) next to the search box to save the current expression into the **Saved Expressions** accordion for permanent reuse.
5. **Reuse Session History:** Expand the **Session History** accordion to view previous searches from your current login session (formatted as `#N (BASE) T=XX : ?expression`). Click any entry to re-run it immediately, or click **Clear** to wipe the session history.
6. **Reset Results & View Help:** Click the **Eraser** button (`fa-eraser`) to clear the search input, hide the current result list, and restore the built-in quick syntax help guide.

---

## 📝 Supported Syntax

The plugin natively supports individual operators as well as complex boolean combinations:

### 1. Field Presence & Absence (`p` / `a`)
Checks whether a specific field tag is present or absent in the record.
- *Syntax:* `?p(vXX)` or `?a(vXX)`
- *Examples:*
  - `?p(v100)` — Retrieves all records where field `100` is present and previews field `100` in the sidebar.
  - `?a(v245)` — Retrieves all records where field `245` is absent.

### 2. Relational MFN Filter (`mfn`)
Filters records based on their Master File Number (MFN) using relational operators (`>`, `<`, `>=`, `<=`, `=`, `<>` or `!=`).
- *Syntax:* `?mfn > XX`, `?mfn < XX`, `?mfn = XX`
- *Examples:*
  - `?mfn>3` — Retrieves all records with an MFN greater than 3.
  - `?mfn<=50` — Retrieves all records up to MFN 50.

### 3. String / Substring Search (`vXX:"text"`)
Searches for a case-insensitive character string inside a specific field or across the entire record (`v0`). Supports both double quotes (`"..."`) and single quotes (`'...'`).
- *Syntax:* `?vXX:"text"` or `?v0:"text"`
- *Examples:*
  - `?v245:"History"` — Retrieves records containing `"History"` in field `245`.
  - `?v0:"Petrarca"` — Searches across all fields in the record (`v0`) for `"Petrarca"`.

### 4. MFN Range Modifier (`*start,end`)
Restricts any search expression to a specific range of MFNs to accelerate sequential scanning on large databases.
- *Syntax:* `?*start,end <expression>`
- *Examples:*
  - `?*10,125 v0:"Petrarca"` — Scans only MFNs `10` through `125` for the string `"Petrarca"`.

### 5. Boolean Combinations (`AND`, `OR`, `NOT` & Parentheses)
Combine any of the syntaxes above using boolean keywords (`AND`, `OR`, `NOT`) or classic Winisis symbols (`*`, `+`, `^`) along with parentheses `(...)`. When multiple fields are queried, the sidebar preview automatically labels and displays the content of each matched field (e.g., `[v10] ... [v20] ...`).
- *Examples:*
  - `?v10:"Maria" AND v20:"Brasil"`
  - `?p(v100) AND NOT p(v500)`
  - `?*1,500 (v100:"Silva" OR v100:"Santos") AND mfn>10`

---

## 💾 Where Search Expressions Are Stored

The plugin manages search expressions using a dual-layer storage architecture:

1. **Session History (Server-Side PHP Session):**
   - **Location:** Stored in the server's active PHP session under `$_SESSION['like_winisis_history']` and mirrored to ABCD's native `$_SESSION['history']` array.
   - **Lifecycle:** Temporary. Expressions remain available across database switches during the user's active login session and are automatically cleared when the session ends, when the user logs out, or when clicking the **Clear** link in the Session History panel.
   - **Integration:** Because it mirrors to `$_SESSION['history']`, queries executed in the plugin are also visible in ABCD's native **Search History** (`search_history.php`) tool.

2. **Saved Expressions / Bookmarks (Client-Side Browser Storage):**
   - **Location:** Stored persistently in the user's web browser via the `localStorage` API under the key `abcd_like_winisis_saved_expressions`.
   - **Lifecycle:** Permanent per browser/workstation. Bookmarked expressions persist across logouts and browser restarts until manually deleted by clicking the `×` button next to the saved item.

---

## 🛡️ Compatibility & System Requirements

- **ABCD System:** Version **4.1.0** or higher (`"requires": "4.1.0"` in `plugin.json`).
  - *Note:* Versions **4.0.2 and earlier are not supported**, as this plugin relies on the structural hooks `dataentry_toolbar_search` and `dataentry_footer` introduced in ABCD v4.1.0. The ABCD Plugin Manager automatically verifies `version.php` and prevents activation on incompatible core versions.
- **PHP:** Version **8.3+** (compatible with strict typing and `match` expressions).
- **Architecture:** Built upon the ABCD Official Plugin Ecosystem using `PluginBridge`, `LanguageManager`, and `hooks.php` without modifying core logic.

---

## 📦 Installation

### Option A: Via ABCD Official Plugin Repository (Recommended)
1. Log into your ABCD administrative panel and navigate to **Configuration > Plugin Manager** (`plugins_manager.php`).
2. Locate **Like Winisis Search** under the **ABCD Official Repository** section and click **Install**.
3. Once installed, click **Activate** in the **Installed Plugins** table.

### Option B: Manual Installation
1. Download the compressed release package (`.zip`) from the repository Releases.
2. Extract the `like-winisis` folder into your ABCD content plugins directory:
   `/htdocs/content/plugins/like-winisis/`
3. Log into your ABCD administrative panel and navigate to **Configuration > Plugin Manager** (`plugins_manager.php`).
4. Locate **Like Winisis Search** and click **Activate**.