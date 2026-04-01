# Pohoda MCP Server

MCP server pro účetní software Pohoda (mServer XML API).

## Architektura

- `server.php` -- vstupní bod; skládá MCP server (`mcp/sdk`) přes inline anonymní `Psr\Container` wrapper, který MCP serveru při každém `get(McpTools::class)` dodá `McpTools` s injektovaným sdíleným `PohodaClient`. Attribute discovery běží nad `src/`. Pokud jsou nastaveny env `POHODA_EXE_PATH` a `POHODA_CONFIG_NAME`, předá se `PohodaClient`u `MServerController` pro lazy autostart mServeru.
- `src/McpTools.php` -- tenký MCP adaptér s `#[McpTool]` atributy, mapuje MCP parametry na PohodaClient metody
- `src/PohodaClient.php` -- HTTP klient pro mServer, doménové metody (createInvoice, createStock, ...), XML stavba přes XmlBuilder. Volitelně drží `MServerController` (přes `setController()`) a před každým HTTP requestem ověřuje, že mServer běží — pokud ne, sám ho přes controller spustí. Při destrukci ho zase zastaví, ale jen pokud ho sám startoval.
- `src/XmlBuilder.php` -- staví Pohoda XML požadavky pomocí XMLWriter, data jako vnořené PHP pole
- `src/Response.php` -- parsovaná odpověď z mServeru s `isOk()`, `toArray()`, seznam `ResponseItem`
- `src/ResponseItem.php` -- jeden záznam z odpovědi s `id`, `state`, `data`, `isOk()`
- `src/MServerController.php` -- spouští/zastavuje Pohoda mServer přes `pohoda.exe /HTTP`, non-blocking launch + polling `PohodaClient::getStatus()`

## Pohoda mServer API

- Komunikace přes HTTP POST na `/xml` endpoint
- Autentizace hlavičkou `STW-Authorization: Basic {base64(user:pass)}`
- Požadavky zabaleny v `<dat:dataPack>` obálce s ICO firmy
- Odpovědi v `<rsp:responsePack>` s atributem `state="ok"|"error"`
- XML namespace schémata: `http://www.stormware.cz/schema/version_2/*.xsd`
- Kódování: Windows-1250 v XML deklaraci

## Schéma a agendy

- Agenda `invoice` vyžaduje atribut `invoiceType` (issuedInvoice/receivedInvoice), jinak mServer vrátí error
- Agenda `addressbook` má `versionAttr` = `addressBookVersion` (camelCase), ostatní agendy mají `{agenda}Version`
- Filtry pro doklady: id, dateFrom, dateTill, selectedCompanys, selectedIco, selectedNumbers, lastChanges
- Pozor na namespace ve filtru `selectedNumbers`: obaly `ftr:number` a `ftr:selectedNumbers` jsou v `ftr:`, ale vnořený `typ:numberRequested` je v `typ:` (schéma sdílí strukturu s datovým typem `typ:number`, který pojmenovává hodnotu `numberRequested`). Záměna za `ftr:numberRequested` způsobí schema validation error.
- Filtry pro zásoby: id, code, name, EAN, PLU, storage, store, internet, lastChanges
- `contract` používá vlastní namespace `lCon` a `list_contract.xsd`, ostatní dokladové agendy sdílejí `lst` a `list.xsd`
- `centre` používá `lCen`/`list_centre.xsd`, `activity` používá `lAcv`/`list_activity.xsd`
- Tisk přes `<prn:print>` požaduje agendu česky (`vydane_faktury`, `zasoby`, ...) a ID tiskové sestavy; podporuje čtyři výstupy: na tiskárnu, PDF soubor, PDF jako Base64 v odpovědi, odeslání emailem
- Editace faktur přes XML API není podporována; zásoby a objednávky mají `actionType` pro update

## HTTP a lokální IO

- Status endpoint `/status` vrací plain text, ne XML — `PohodaClient::getStatus()` to ošetřuje tak, že při neparsovatelné odpovědi vrátí `['message' => $text]`
- mServer zpracovává požadavky sekvenčně a souběžné requesty serializuje (empiricky, Stormware to explicitně nedokumentuje)
- mServer naslouchá jen na IPv4; PHP `file_get_contents`/`fsockopen` s hostem `localhost` jde nejdřív na IPv6 (`::1`) a čeká na timeout — používat `127.0.0.1`. Curl si IPv4 fallback zařídí sám, proto `PohodaClient` přes curl funguje i s `localhost`.

## Správa procesu (CLI a MServerController)

- mServer lze ovládat z CLI: `pohoda.exe /HTTP start|stop|restart "<konfigurace>"`. `stop /f` vynutí ukončení.
- `pohoda.exe /HTTP list` a `list:xml` empiricky nevracejí nic do stdoutu (aspoň u Pohody 2018) — nelze se na ně spoléhat pro programové zjištění stavu; místo toho polluj `PohodaClient::getStatus()`.
- `pohoda.exe /HTTP start "<conf>"` z PHP blokuje `exec()` dokud mServer neskončí (pravděpodobně kvůli nezavřenému stdout handle, ale nepotvrzeno). Workaround v `MServerController`: spouštění přes Windows `start "" /B` (`pclose(popen(..., 'r'))`), aby se proces odpojil.
- **Autostart v `PohodaClient::ensureRunning()`** je rekurzně bezpečný: nastaví `$started = true` **před** voláním `MServerController::start()`. Controller v polling smyčce volá `$client->getStatus()` → `httpRequest()` → `ensureRunning()`, ale `$started` flag rekurzi přeskočí. Při výjimce se flag resetuje, aby další tool call mohl autostart zopakovat.
- `__destruct` zastaví mServer pouze pokud `$controllerStarted = true`, tj. pokud autostart skutečně proběhl. Pokud mServer už běžel při prvním tool callu, neukončujeme ho.

## Konvence XML builderu

- Data se předávají jako vnořené PHP pole, klíče jsou prefixované namespace (např. `'inv:date' => '2024-01-15'`)
- `null`, `''` a `[]` hodnoty se přeskakují
- Booleans se píší jako `'true'`/`'false'`
- `@`-prefixované klíče v poli jsou XML atributy (např. `'@agenda' => 'vydane_faktury'`)
- Numerické klíče slouží pro opakované elementy (pole polí)

## Testy

- Unit testy v `tests/*.phpt` přes Nette Tester (`tests/Response.phpt`, `tests/XmlBuilder.phpt`). Spouští se `vendor/bin/tester tests`.
- `tests/bootstrap.php` inicializuje Tester; `tests/output/` je generovaný (neversionovaný).
- Smoke test MCP serveru přes stdio transport (neprovádí skutečné HTTP volání na mServer, jen inicializuje protocol):

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' | \
  POHODA_URL=http://127.0.0.1:444 POHODA_ICO=12345678 POHODA_USERNAME=Admin POHODA_PASSWORD= \
  php server.php
```
