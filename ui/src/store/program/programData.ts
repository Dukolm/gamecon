import { ProgramStateCreator } from ".";
import { Aktivita, fetchAktivity } from "../../api/program";

// TODO: vyřešit politiku updatů programu pro cache

export type ProgramDataSlice = {
  data: {
    aktivityPodleRoku: {
      [rok: number]: Aktivita[],
    },
  }
  /** Pokud ještě není dotažený tak dotáhne rok */
  načtiRok(rok: number, načtiZnova?: boolean): Promise<void>;
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = (set, get) => ({
  data: {
    aktivityPodleRoku: {},

  },
  async načtiRok(rok: number, načtiZnova = false) {
    const dotaženo = !!get().data.aktivityPodleRoku[rok];
    if (dotaženo && !načtiZnova) return;

    const aktivity = await fetchAktivity(rok);
    set(s => { s.data.aktivityPodleRoku[rok] = aktivity; });
  },
});
