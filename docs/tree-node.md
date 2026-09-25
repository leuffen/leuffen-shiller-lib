# TreeNode-Konvention v1

Dieser Datenvertrag ist der gemeinsame Entwurf für Schillers Datei- und Seitenbäume und kann in anderen Projekten mit eigenen Nutzdaten übernommen werden. Die Library und die hier beschriebenen Projektionen sind noch nicht implementiert.

## Welcher Standard ist das?

**Datenformat: TreeNode-Konvention v1, angelehnt an MUI Rich Tree View.** MUI verwendet standardmäßig `id`, `label` und `children` und unterstützt bei anderen Feldnamen die Zugriffsfunktionen `getItemId`, `getItemLabel` und `getItemChildren`. Unser zusätzlicher `data`-Bereich bündelt fachliche Nutzdaten. Diese Konvention ist kein allgemeiner JSON-, W3C- oder herstellerübergreifender Standard. Sie benötigt keine MUI-Abhängigkeit im Backend. Quelle: [MUI: Rich Tree View – Items](https://mui.com/x/react-tree-view/rich-tree-view/items/).

**Zugängliche Darstellung: WAI-ARIA.** Rollen wie `tree`, `treeitem` und `group` sowie Zustände wie `aria-expanded` beschreiben die gerenderte Oberfläche. Das W3C-Tree-View-Pattern erläutert dazu Tastaturbedienung, Fokus und Auswahl; es definiert kein JSON-Datenformat. Der Renderer setzt diese Regeln um. Quelle: [W3C: Tree View Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/treeview/).

Andere Projekte können auf diese Datei als **„TreeNode-Konvention v1“** verweisen und den folgenden generischen Typ übernehmen. Eine Übernahme in andere Repositories oder ein gemeinsames npm-Paket ist damit noch nicht erfolgt.

## Generischer Vertrag

```ts
export interface TreeNode<TData = Record<string, unknown>> {
  id: string;
  label: string;
  children: TreeNode<TData>[];
  data: TData;
}
```

| Feld | Verbindliche Bedeutung |
|---|---|
| `id` | Nicht leer und innerhalb eines Baums eindeutig; unabhängig vom Anzeigenamen. |
| `label` | Anzeigename als Text, kein HTML; muss nicht eindeutig sein. |
| `children` | Geordnete Liste der geladenen direkten Unterknoten; immer ein Array. |
| `data` | JSON-Objekt mit den typisierten Nutzdaten des jeweiligen Projekts. |

Ein Renderer braucht zum vollständigen Grundaufbau nur id, label und children. Eigene Spalten, Symbole, Menüs oder Links lesen data. Der Vertrag enthält keine zyklischen Elternreferenzen, Klasseninstanzen oder Funktionen. Auswahl, Fokus und aufgeklappte IDs verwaltet das Frontend getrennt; der Serverbaum bleibt ein Lesesnapshot.

Bei vollständig geladenen Bäumen bedeutet `children: []` ein Blatt. Teilweise geladene Daten brauchen zusätzlich ein explizites Nachladeprofil; Schillers Profil steht unten. Eine beliebige Komponente unterstützt dieses Nachladen nicht automatisch.

## Schiller-Profil

Die folgende TypeScript-Beschreibung gilt für die JSON-Ausgabe, nicht für PHP-Document-Objekte:

```ts
export type JsonValue =
  | null | boolean | number | string
  | JsonValue[] | { [key: string]: JsonValue };

export type FileKind =
  | "directory" | "page" | "asset" | "data" | "template" | "other";

export interface FileEntry {
  path: string;
  kind: FileKind;
}

export interface TranslationInfo {
  language: string;
  path: string;
  exists: boolean;
  isRootDocument: boolean;
  published: boolean | null;
}

export interface SchillerTreeData {
  kind: FileKind;
  path: string | null;
  file: FileEntry | null;
  metadata: Record<string, JsonValue>;
  translations: Record<string, TranslationInfo>;
  hasChildren: boolean;
  childrenLoaded: boolean;
}

export type SchillerTreeNode = TreeNode<SchillerTreeData>;

export interface PageTreePayload {
  root: SchillerTreeNode;
  diagnostics: unknown[]; // Diagnose-DTO ist nicht Teil dieser Baumkonvention.
}

export interface FileListingPayload {
  entries: SchillerTreeNode[];
  diagnostics: unknown[];
}
```

PHP verwendet für data den vorgeschlagenen Typ `SchillerTreeData`; die Signaturen stehen in [Proposal § 10](proposals/2026-09-12-schiller-seiten-api.md). Leere metadata/translations werden im JSON als `{}` serialisiert, leere children/entries als `[]`. FileKind wird als String ausgegeben. `toArray()` liefert dafür eine gezielte Projektion, keinen generischen Dump von PHP-Objekten: insbesondere keine Bodies, Document-Referenzen, Storage-Verbindungen oder adapterState.

| Bedeutung | Seitenbaum | Physisches Dateilisting |
|---|---|---|
| ID | `/leistungen/diagnostik` | `file:leistungen/diagnostik.md` |
| `data.path` | `null` | Root-relativer Quellpfad |
| `data.file` | Bevorzugte lesbare Seitendatei oder `null` | Datei oder `null` bei Ordnern |
| `data.kind` | `page` bei eigener Seite, sonst `directory` | Tatsächliche Eintragsart |
| `data.translations` | Alle lesbaren konfigurierten Sprachen mit exists | `{}` |
| `getDocument()` in PHP | Zugeordnete Seite oder `null` | Immer `null` |

Die physische ID entsteht aus `file:` plus kanonischem Root-relativem Pfad ohne führenden Slash; das physische Root hätte die ID `file:` und den Pfad `""`. Physische IDs sind keine Argumente für getPage oder rename. Zusammengeführte Bäume verschiedener Sites brauchen durch die Anwendung einen zusätzlichen Site-Namensraum. Innerhalb derselben Site kollidieren Seiten- und Datei-IDs nicht.

Seiten-IDs bleiben beim Wechsel von Blattdatei zu index erhalten, ändern sich aber bei Rename/Move samt Nachfahren-Präfix. Physische IDs ändern sich mit dem Pfad. Nach Strukturänderungen lädt die UI den Baum neu und aktualisiert ihre Auswahl; der Vertrag behauptet keine unveränderlichen UUIDs.

## Anzeigenamen und Adapter

Für einen Seitenknoten verwendet label den nicht leeren effektiven `title` der bevorzugten lesbaren Seitendatei. Bevorzugt wird die Standardsprache, sonst die erste vorhandene lesbare Variante in der konfigurierten Sprachreihenfolge. Fehlt ein gültiger Texttitel, folgt eine vorhandene lesbare Kategoriebezeichnung des Adapters; zuletzt das letzte ID-Segment beziehungsweise `/` am Root. Keine automatische Großschreibung und keine Body-Auswertung.

JekyllLegacyAdapter normalisiert die vorhandene _section.yml-Bezeichnung als Kategoriebezeichnung; weitere erlaubte Angaben bleiben in data.metadata erhalten. JekyllPolyglotAdapter ordnet Index-/Blattdateien dem Seitenknoten zu und nutzt für reine Ordner den ID-Fallback. Im physischen Listing ist label immer der letzte Pfadbestandteil, am Root `/`. Beispiele und Tests dürfen keine Titel aus verborgenen Varianten ableiten.

Die Adapter liefern vorhandene Sprachvarianten in data.translations; Schiller ergänzt erlaubte fehlende Sprachen mit exists=false. Eine reine Kategorie ohne Seitengruppe hat data.file=null und translations={}. Metadaten und Labels sind Anzeigeangaben, keine Schreibanweisungen: Eine Änderung im Browser speichert weder einen Seitentitel noch einen Dateinamen. Dafür verwendet die Anwendung die Document- oder Rename-API.

## Aufklappen und Öffnen

Ein Knoten mit data.file und Kindern ist zugleich Seite und Elternknoten. Der Aufklapp-Pfeil ändert den UI-Zustand; das Öffnen der Seite führt separat zum Editor. Eine reine Kategorie öffnet keinen Seiteneditor, bleibt aber fokussierbar und aufklappbar. Sie darf dafür nicht pauschal als disabled markiert werden. Dateiöffnung in der physischen Ansicht ist eine separate Anwendungsaktion.

Schiller liefert nur lesbare Kinder. `data.hasChildren` sagt, ob mindestens ein lesbares direktes Kind existiert. `data.childrenLoaded` sagt, ob sämtliche lesbaren direkten Kinder bereits enthalten sind. `TreeNode::isLeaf()` ist die PHP-Komfortmethode für `!data.hasChildren`.

| Zustand | children | hasChildren | childrenLoaded |
|---|---|---|---|
| Vollständig geladenes Blatt / leerer Ordner | [] | false | true |
| Vollständig geladener Elternknoten | Unterknoten | true | true |
| Ordner mit noch nicht geladenen lesbaren Kindern | [] | true | false |

`pages()` und `files(..., recursive: true)` liefern vollständig geladene Teilbäume. Bei `files(..., recursive: false)` lädt die Anwendung beim Aufklappen mit `files(node.data.path)` die nächste Ebene und setzt deren entries als children des Knotens. Die anschließende lokale Anzeige markiert seine direkten Kinder als geladen. Es gibt in v1 keine Pagination. Ein Ordner mit ausschließlich verborgenen Kindern erscheint als leer; der Kinderstatus verrät deren Existenz nicht.

## Vom PHP-Listing zum Frontend

[Beispiel 04](../examples/04-list-pages.php) erzeugt die PageTree-Projektion und JSON; der vorhandene HTTP-Endpunkt der Anwendung sendet dieses JSON als `application/json`. Die Antwort enthält `root` und `diagnostics`. Es ist kein neuer fest eingebauter Schiller-HTTP-Endpunkt vorgesehen.

In einer bestehenden React-Anwendung mit eingerichtetem [MUI X Tree View](https://mui.com/x/react-tree-view/quickstart/) genügt dieser Komponentenausschnitt. `PageTreePayload` ist der oben definierte Typ; die Anwendung übergibt ihre bereits empfangene JSON-Antwort als payload:

```tsx
import { RichTreeView } from "@mui/x-tree-view/RichTreeView";

function PageTreeView({ payload }: { payload: PageTreePayload }) {
  return <RichTreeView items={[payload.root]} />;
}
```

Das rendert den Root-Knoten und seine Unterknoten ohne rekursive Umformung. Für die Basisfixture aus Beispiel 04 erscheinen `leistungen` und darunter `Diagnostik`. Das Beispiel zeigt die Baumdarstellung; Editoraktionen, Sprachspalten und die Anzeige von diagnostics bindet die Anwendung zusätzlich an. Die MUI-Integration ist nicht als ausgeführter Test behauptet.

Für das vollständige physische Listing aus [Beispiel 03](../examples/03-list-files.php) lautet die Übergabe `items={payload.entries}`. Komponenten mit anderen Feldnamen benötigen passende Zugriffsfunktionen oder eine kleine Feldzuordnung; universelle Drop-in-Kompatibilität ist nicht zugesagt. Insbesondere berücksichtigt das einfache MUI-Beispiel Schillers Nachladeprofil nicht: dafür entweder vollständig laden oder die Nachlade-API des gewählten Renderers anbinden.

## Spätere Vertragsprüfung

Die Implementierung muss beide Adapter und das Dateilisting gegen dieselbe Struktur prüfen: eindeutige IDs, label als Text, geordnete Kinder, null-Datei bei Kategorien, vollständige lesbare Sprachzustände, Erhalt erlaubter Metadaten und sichere JSON-Projektion. Außerdem gehören vollständige und teilweise geladene Ordner sowie voneinander unabhängiges Aufklappen und Seitenöffnen in die Abnahme. JSON-Konformität allein belegt keine zugängliche UI.
