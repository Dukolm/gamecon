import { StateCreator } from "zustand";
import create from "zustand";
import { devtools } from "zustand/middleware";
import { immer } from "zustand/middleware/immer";

export type ZustandMutators = [
  // redux dev tools extension do prohlížeče
  ["zustand/devtools", never],
  ["zustand/immer", never],
];

export type MyStateCreator<State, T> = StateCreator<State, ZustandMutators, [], T>;

// TODO: funguje devtools i v produkci ? zařídit aby nejelo (pravďepodobně pomocí druhého argumentu devtools funkce)
export const createMyStore = <State>(createState: MyStateCreator<State, State>) =>
  create<State>()(
    devtools(immer((...args) => ({ ...createState(...args) })))
  );

