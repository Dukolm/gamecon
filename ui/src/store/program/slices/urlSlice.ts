import { ProgramStateCreator, useProgramStore } from "..";
import { generujUrl, parsujUrl, ProgramTabulkaVýběr, ProgramURLState, urlStateProgramTabulkaMožnostíDnyMůj, URL_STATE_VÝCHOZÍ_MOŽNOST, URL_STATE_VÝCHOZÍ_STAV } from "../logic/url";


export type ProgramUrlSlice = {
  urlState: ProgramURLState
  urlStateMožnosti: {
    dny: ProgramTabulkaVýběr[],
    linie: string[],
  },
}

export const createProgramUrlSlice: ProgramStateCreator<ProgramUrlSlice> = () => ({
  urlState: URL_STATE_VÝCHOZÍ_STAV,
  urlStateMožnosti: {
    dny: urlStateProgramTabulkaMožnostíDnyMůj(),
    linie: [],
  }
});



/** nastaví url a url-stav na hodnotu */
const nastavUrlState = (url: string) => {
  useProgramStore.setState(s => {
    s.urlState = parsujUrl(url);
  }, undefined, "nastavUrlState");
};


export const nastavStateZUrl = () => {
  nastavUrlState(location.href);
};

export const nastavUrlZState = (replace = false) => {
  const současnéUrl = location.href;
  const novéUrl = generujUrl(useProgramStore.getState().urlState);

  /** stavy jsou ekvivalentní, netřeba cokoliv měnit */
  if (současnéUrl === novéUrl || !novéUrl) return;

  history[!replace ? "pushState" : "replaceState"](null, "", novéUrl);
};


export const nastavUrlAktivitaNáhledId = (aktivitaNáhledId: number) => {
  useProgramStore.setState(
    (s) => {
      s.urlState.aktivitaNáhledId = aktivitaNáhledId;
    },
    undefined,
    "nastav url nahled id"
  );
};

export const skryjAktivitaNáhledId = () => {
  useProgramStore.setState((s) => {
    s.urlState.aktivitaNáhledId = undefined;
  });
};

export const nastavUrlVýběr = (možnost: ProgramTabulkaVýběr) => {
  useProgramStore.setState((s) => {
    s.urlState.výběr = možnost;
  }, undefined, "nastav program den");
};

export const nastavFiltrLinie = (linie: string, hodnota: boolean) => {
  useProgramStore.setState((s) => {
    if (hodnota) {
      const filtrLinie = s.urlState.filtrLinie ?? [];
      if (!s.urlState.filtrLinie)
        s.urlState.filtrLinie = filtrLinie;

      filtrLinie.push(linie);
      if (!s.urlStateMožnosti.linie.some(x => !filtrLinie.some(y => x === y))) {
        s.urlState.filtrLinie = undefined;
      }
    } else {
      s.urlState.filtrLinie = (s.urlState.filtrLinie ?? s.urlStateMožnosti.linie).filter(x => x !== linie);
    }
  }, undefined, "nastav program linie");
};
