import { FunctionComponent } from "preact";

type TProgramWrapperProps = {
  children:JSXElement
};

/**
 * V dev serveru simuluje okolí gamecon webu pro testování programu
 */
export const ProgramWrapper: FunctionComponent<TProgramWrapperProps> = (
  props
) => {
  const {children} = props;

  return (
    <>
      <div class="menu">
        <div class="menu_obal">
          <div class="menu_obal2">
            <a href="." class="menu_nazev">
              GameCon
            </a>

            <div class="menu_uzivatel">
              <div class="menu_jmeno">uživatel</div>
              <div class="menu_uzivatelpolozky">
                <a href="finance">Finance</a>
                <a href="registrace">Nastavení</a>
                <a href="prihlaska">Přihláška na GC</a>
                <a href="#">Odhlásit</a>
                <form id="odhlasForm" method="post" action="prihlaseni"></form>
              </div>
            </div>

            <div class="menu_menu">
              <a href="program" class="menu_odkaz">
                program
              </a>
              <div class="menu_kategorie">
                <div class="menu_nazevkategorie">aktivity</div>
                <div class="menu_polozky">
                  <a href="deskoherna" class="menu_polozka">
                    deskoherna
                  </a>
                  <a href="turnaje" class="menu_polozka">
                    turnaje v deskovkách
                  </a>
                  <a href="epic" class="menu_polozka">
                    epické deskovky
                  </a>
                  <a href="wargaming" class="menu_polozka">
                    wargaming
                  </a>
                  <a href="larpy" class="menu_polozka">
                    larpy
                  </a>
                  <a href="rpg" class="menu_polozka">
                    RPG
                  </a>
                  <a href="drd" class="menu_polozka">
                    mistrovství v DrD
                  </a>
                  <a href="legendy" class="menu_polozka">
                    legendy klubu dobrodruhů
                  </a>
                  <a href="bonusy" class="menu_polozka">
                    akční a bonusové aktivity
                  </a>
                  <a href="prednasky" class="menu_polozka">
                    Přednášky
                  </a>
                  <a href="doprovodny-program" class="menu_polozka">
                    doprovodný program
                  </a>
                </div>
              </div>
              <div class="menu_kategorie">
                <div class="menu_nazevkategorie">informace</div>
                <div class="menu_polozky">
                  <a class="menu_polozka" href="prihlaska">
                    Přihláška
                  </a>
                  <a class="menu_polozka" href="novinky">
                    Novinky
                  </a>
                  <a class="menu_polozka" href="blog">
                    Blog
                  </a>
                  <a class="menu_polozka" href="organizacni-vypomoc">
                    Chci pomoct s GameConem
                  </a>
                  <a class="menu_polozka" href="chci-se-prihlasit">
                    Chci na GameCon
                  </a>
                  <a class="menu_polozka" href="prakticke-informace">
                    Praktické informace
                  </a>
                  <a class="menu_polozka" href="celohra">
                    Celohra
                  </a>
                  <a class="menu_polozka" href="kontakty">
                    Kontakty
                  </a>
                  <a class="menu_polozka" href="info-po-gc">
                    Info po GC a zpětná vazba
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      {children}
    </>
  );
};

ProgramWrapper.displayName = "ProgramWrapper";
