# handygeocaching-service

Tato služba je použita jako *API/proxy* mezi **Geocaching.com** stránkami a aplikací [**HandyGeocaching**](https://github.com/arcao/handygeocaching). Většina kódu vznikla velmi dávno, proto je to na kódu znát. :)

## Přístupové body k API
* `old/handy31.php` - Stará verze API, používaná pro:
  * Přihlášení
  * Vyhledání keší podle souřadnic
  * Vyhledání keší podle klíčového slova
  * Získání informací o keši
  * Získání listingu keše
  * Získání nápovědy (hintu) keše
  * Získání prvních logů keše
  * Získání dalších logů keše
  * Získání informací o trackable
  * Získání vzorce pro multisolver
* `api.php` - Nová verze API, používaná pro:
  * Field notes

Základní testování funkcí API lze provést přes webový formulář `tester.php`. 

## Autor
* [David Vávra](https://github.com/davidvavra) (tvůrce)
* [Martin Sloup /Arcao/](https://github.com/arcao) (udržovatel)





