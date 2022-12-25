import { useProgramStore } from "."

export const useProgramRok = (rok: number) =>
  useProgramStore(s=>s.data.aktivityPodleRoku[rok]);
