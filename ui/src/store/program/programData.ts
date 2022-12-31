import { ProgramStateCreator } from ".";
import { Aktivita, AktivitaPřihlášen, fetchAktivity, fetchAktivityPřihlášen } from "../../api/program";

// TODO: vyřešit politiku updatů programu pro cache

export type ProgramDataSlice = {
  data: {
    aktivityPodleRoku: {
      [rok: number]: Aktivita[],
    },
    aktivityPřihlášenPodleRoku: {
      [rok: number]: AktivitaPřihlášen[],
    },
    aktivityPodleId: {
      [id: number]: Aktivita,
    }
  }
  /** Pokud ještě není dotažený tak dotáhne rok, příhlášen se dotahuje vždy */
  načtiRok(rok: number, načtiZnova?: boolean): Promise<void>;
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = (set, get) => ({
  data: {
    aktivityPodleRoku: {},
    aktivityPřihlášenPodleRoku: {},
    aktivityPodleId: {},
  },
  async načtiRok(rok: number, načtiZnova = false) {
    const aktivityPřihlášen = await fetchAktivityPřihlášen(rok);

    set(s => {
      s.data.aktivityPřihlášenPodleRoku[rok] = aktivityPřihlášen;
    }, undefined, "dotažení přihlášen-aktivity");

    const dotaženo = !!get().data.aktivityPodleRoku[rok];
    if (dotaženo && !načtiZnova) return;

    const aktivity = await fetchAktivity(rok);
    set(s => {
      s.data.aktivityPodleRoku[rok] = aktivity;
      for (const aktivita of aktivity) {
        s.data.aktivityPodleId[aktivita.id] = aktivita;
      }
    }, undefined, "dotažení aktivit");
  },
});
