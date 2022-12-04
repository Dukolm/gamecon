/*
 * Konstanty předané ze serveru společně se scriptem.
 * Liší se pro testovací server
 */

import { range } from "./utils";


type GameconKonstanty = {
  IS_DEV_SERVER: boolean,
  /**
   * cesta k této stráce v rámci které se preact využívá.
   * například /web/program/
   */
  BASE_PATH_PAGE: string,
  /**
   * cesta k api
   * například /web/api/
   */
  BASE_PATH_API: string,
  ROK: number,
  PROGRAM_OD: number,
  PROGRAM_DO: number,
  PROGRAM_DNY: number[],
  PROGRAM_ŘAZENÍ_LINIE: string[],
  LEGENDA: string,
}

declare global {
  // interface se automaticky propojí s existujícím 
  //   proto je nutné použít interface a né type
  // eslint-disable-next-line @typescript-eslint/consistent-type-definitions
  interface Window {
    GAMECON_KONSTANTY: Partial<GameconKonstanty>;
    preactMost: {
      obchod: {
        show?: (() => void) | undefined,
      }
    }
  }
}

const GAMECON_KONSTANTY_DEFAULT: GameconKonstanty = {
  IS_DEV_SERVER: false,
  BASE_PATH_PAGE: "/",
  BASE_PATH_API: "/api/",
  ROK: 2022,
  PROGRAM_OD: 1658268000000,
  PROGRAM_DO: 1658689200000,
  PROGRAM_DNY: [],
  LEGENDA: "",
  PROGRAM_ŘAZENÍ_LINIE: [
    "brigádnické", "workshopy", "(bez typu – organizační)",
    "organizační výpomoc", "deskoherna", "turnaje v deskovkách",
    "epické deskovky", "wargaming", "larpy", "RPG",
    "mistrovství v DrD", "legendy klubu dobrodruhů",
    "akční a bonusové aktivity", "Přednášky", "doprovodný program"
  ],
};

export const GAMECON_KONSTANTY = {
  ...GAMECON_KONSTANTY_DEFAULT,
  ...window.GAMECON_KONSTANTY,
};

const ČAS_DEN = 24 * 60 * 60 * 1000;
GAMECON_KONSTANTY.PROGRAM_DNY = range(GAMECON_KONSTANTY.PROGRAM_OD, GAMECON_KONSTANTY.PROGRAM_DO, ČAS_DEN).reverse();

/** Roky ve kterých se gamecon konal */
export const ROKY = range(2009, GAMECON_KONSTANTY.ROK).filter(x => x !== 2020);

export const initEnv = () => {
  window.preactMost = {
    obchod: {
    }
  };
};