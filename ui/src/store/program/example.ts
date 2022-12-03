import { ProgramStateCreator } from ".";
import { Aktivita } from "../../api/program";

export type ProgramDataSlice = {
  data: {
    aktivityPodleRoku: {
      [rok: number]: Aktivita[],
    },
    načtiRok(rok: number): void;
  }
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = (set, get) => ({
  data: {
    aktivityPodleRoku: {},
    načtiRok(rok: number) {
      // TODO:
    },
  },
});
