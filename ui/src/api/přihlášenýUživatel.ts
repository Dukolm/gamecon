import { GAMECON_KONSTANTY } from "../env";

export type PřihlášenýUživatel = {
  organizator?: boolean,
  prihlasen?: boolean,
  koncovkaDlePohlaví?: string,
}


export const fetchPřihlášenýUživatel = async (): Promise<PřihlášenýUživatel> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}prihlasenyUzivatel`;
  return fetch(url, { method: "POST" }).then(async x => x.json());
};
