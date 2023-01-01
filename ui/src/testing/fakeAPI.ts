import { Aktivita, AktivitaPřihlášen, LoginStav } from "../api/program";
import { sleep } from "../utils";

export const fetchTestovacíAktivity = async (rok: number): Promise<Aktivita[]> => {
  const res = await fetch("/testing/aktivityProgram.json");
  const json = await res.json() as { [rok: number]: Aktivita[] };
  return json[rok] ?? [];
};

export const fetchTestovacíAktivityPřihlášen = async (rok: number): Promise<AktivitaPřihlášen[]> => {
  return (await fetchTestovacíAktivity(rok)).map(x => ({ id: x.id, mistnost: `místnost 5`, prihlaseno: Math.random() < .3, vedu: Math.random() < .1, slevaNasobic: Math.random() < .1 ? undefined : Math.floor(Math.random() * 10) / 10 } as AktivitaPřihlášen));
};

export const fetchTestovacíLoginStav = async (): Promise<LoginStav> => {
  await sleep(0);
  return {
    organizator: Math.random() < .3,
    přihlášen: Math.random() < .5,
  };
};

