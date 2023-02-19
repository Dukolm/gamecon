import { useProgramStore } from ".";
import { Pohlavi, PřihlášenýUživatel } from "../../api/přihlášenýUživatel";
import { ProgramTabulkaVýběr, ProgramURLState } from "./logic/url";
import { Aktivita, filtrujDotaženéAktivity, jeAktivitaDotažená } from "./slices/programDataSlice";

// TODO: přidat zbytek filtrů
export const useAktivityFiltrované = (): Aktivita[] => {
  const urlState = useProgramStore((s) => s.urlState);
  const aktivityRočníku = useProgramStore(
    (s) => filtrujDotaženéAktivity(s.data.aktivityPodleId).filter(x => new Date(x.cas.od).getFullYear() === urlState.ročník)
  );

  let aktivityFiltrované = aktivityRočníku.filter((aktivita) =>
    urlState.výběr.typ === "můj"
      ? aktivita?.stavPrihlaseni != undefined
      : new Date(aktivita.cas.od).getDay() === urlState.výběr.datum.getDay()
  );

  const filtrLinie = urlState.filtrLinie;
  
  if (filtrLinie) {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        filtrLinie.some(x => x === aktivita.linie)
      );
  }

  const filtrTagy = urlState.filtrTagy;
  
  if (filtrTagy) {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        filtrTagy.some(x => aktivita.stitky.some(stitek=>stitek===x))
      );
  }

  if (urlState.filtrPřihlašovatelné) {
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        aktivita.prihlasovatelna && !aktivita.probehnuta
      );
  }

  return aktivityFiltrované;
};

export const useAktivita = (akitivitaId: number): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[akitivitaId];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  });


export const useAktivitaNáhled = (): Aktivita | undefined =>
  useProgramStore(s => {
    const aktivita = s.data.aktivityPodleId[s.urlState.aktivitaNáhledId ?? -1];
    return jeAktivitaDotažená(aktivita) ? aktivita : undefined;
  });

export const useUrlState = (): ProgramURLState => useProgramStore(s => s.urlState);
export const useUrlVýběr = (): ProgramTabulkaVýběr => useProgramStore((s) => s.urlState.výběr);
export const useUrlStateMožnostiDny = (): ProgramTabulkaVýběr[] => useProgramStore(s => s.urlStateMožnosti.dny);
export const useUrlStateMožnosti = () => useProgramStore(s => s.urlStateMožnosti);

export const useUživatel = (): PřihlášenýUživatel => useProgramStore(s => s.přihlášenýUživatel.data);
export const useUživatelPohlaví = (): Pohlavi | undefined => useProgramStore((s) => s.přihlášenýUživatel.data?.pohlavi);

export const useFiltryOtevřené = (): boolean => useProgramStore(s=>s.všeobecné.filtryOtevřené);

