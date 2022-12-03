import { sleep } from "../utils";


export const fetchPřihlášenýUživatel = async () => {
  await sleep(0);
  return {
    přihlášen: false,
    organizátor: false,
    koncovkaDlePohlaví: "",
  };
};

