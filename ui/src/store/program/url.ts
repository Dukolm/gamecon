import { ProgramStateCreator, useProgramStore } from ".";
import { GAMECON_KONSTANTY } from "../../env";
import { formátujDenVTýdnu, tryParseNumber } from "../../utils";

// TODO: vyřešit politiku updatů programu pro cache
const NÁHLED_QUERY_STRING = "idAktivityNahled";

export type ProgramURLState = {
  výběr: ProgramTabulkaVýběr,
  aktivitaNáhledId?: number,
  rok: number,
}

export type ProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  }
  ;

export type ProgramUrlSlice = {
  urlState: ProgramURLState & {
    možnosti: ProgramTabulkaVýběr[];
  },
}

export const createProgramUrlSlice: ProgramStateCreator<ProgramUrlSlice> = () => ({
  urlState: {
    výběr: {
      typ: "den",
      datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
    },
    možnosti: tabulkaMožnosti(),
    aktivitaNáhledId: undefined,
    // TODO: přidat logiku pro rok v url
    rok: GAMECON_KONSTANTY.ROK,
  },
});

/** vytvoří url z aktuálního url-stavu nebo z předaného stavu */
export const generujUrl = (urlStateParam?: ProgramURLState): string | undefined => {
  const urlState = urlStateParam ?? useProgramStore.getState().urlState;
  const výběr =
    tabulkaMožnosti().find(x => porovnejTabulkaVýběr(x, urlState.výběr));

  if (!výběr) return undefined;

  let url = GAMECON_KONSTANTY.BASE_PATH_PAGE + urlZTabulkaVýběr(výběr);

  const search: string[] = [];

  if (urlState.aktivitaNáhledId)
    search.push(`${NÁHLED_QUERY_STRING}=${urlState.aktivitaNáhledId}`);

  if (search.length)
    url += "?" + search.join("&");

  return url;
};

// TODO: vyextrahovat logiku parsování a genrevoání url do souboru zvlášť
// TODO: lepší přístup parsování a generování url
/** nastaví url a url-stav na hodnotu */
const nastavUrlState = GAMECON_KONSTANTY.IS_DEV_SERVER ? (url: string) => {
  useProgramStore.setState(s => {
    const urlObj = new URL(url);
    const nahledIdStr = tryParseNumber(urlObj.searchParams.get(NÁHLED_QUERY_STRING));
    s.urlState.aktivitaNáhledId = nahledIdStr;

    const den = urlObj.pathname;

    const výběr = tabulkaMožnosti().find(x => urlZTabulkaVýběr(x) === den);
    if (výběr)
      s.urlState.výběr = výběr;
  }, undefined, "DEV nastavUrlState");
} : (url: string) => {
  useProgramStore.setState(s => {
    const basePath = new URL(GAMECON_KONSTANTY.BASE_PATH_PAGE).pathname;
    const urlObj = new URL(url, GAMECON_KONSTANTY.BASE_PATH_PAGE);
    const nahledIdStr = tryParseNumber(urlObj.searchParams.get(NÁHLED_QUERY_STRING));
    s.urlState.aktivitaNáhledId = nahledIdStr;

    const den = urlObj.pathname.slice(basePath.length);

    const výběr = tabulkaMožnosti().find(x => urlZTabulkaVýběr(x) === den);
    if (výběr)
      s.urlState.výběr = výběr;
  }, undefined, "nastavUrlState");
};

// TODO: bude se dotahovat jestli přihlášen
const tabulkaMožnosti = (props?: { přihlášen?: boolean }): ProgramTabulkaVýběr[] =>
  GAMECON_KONSTANTY.PROGRAM_DNY
    .map((den) => ({
      typ: "den",
      datum: new Date(den),
    } as ProgramTabulkaVýběr))
    .concat(...((props?.přihlášen ?? false) ? [{ typ: "můj" } as ProgramTabulkaVýběr] : []));


export const nastavStateZUrl = () =>{
  nastavUrlState(location.href);
};

export const nastavUrlZState = () =>{
  const současnéUrl = location.href;
  const novéUrl = generujUrl();

  /** stavy jsou ekvivalentní, netřeba cokoliv měnit */
  if (současnéUrl === novéUrl || !novéUrl) return;

  history.pushState(null, "", novéUrl);
};

const urlZTabulkaVýběr = (výběr: ProgramTabulkaVýběr) =>
  (výběr.typ === "můj"
    ? "muj_program"
    : formátujDenVTýdnu(výběr.datum));

export const porovnejTabulkaVýběr = (v1: ProgramTabulkaVýběr, v2: ProgramTabulkaVýběr) =>
  urlZTabulkaVýběr(v1) === urlZTabulkaVýběr(v2);
