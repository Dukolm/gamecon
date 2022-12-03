import { Aktivita } from "../api/program";

export const fetchTestovacíAktivity = async (rok: number): Promise<Aktivita[]> =>{
  const res = await fetch("testing/aktivityProgram.json");
  const json = await res.json() as {[rok: number]: Aktivita[]};
  return json[rok] ?? [];
};
