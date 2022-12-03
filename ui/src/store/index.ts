import create, { StateCreator } from "zustand";
import { immer } from "zustand/middleware/immer";
import { devtools } from "zustand/middleware";
import { createExampleSlice, ExmampleSlice } from "./example";


type State = ExmampleSlice;

type Mutators = [
  // redux dev tools extension do prohlížeče
  ["zustand/devtools", never],
  ["zustand/immer", never],
];

export type MyStateCreator<T> = StateCreator<State, Mutators, [], T>;

const createState: MyStateCreator<State> = (...args) => ({
  ...createExampleSlice(...args),
});

// TODO: funguje devtools i v produkci ? zařídit aby nejelo (pravďepodobně pomocí druhého argumentu devtools funkce)
export const useAppStore = create<State>()(
  devtools(immer((...args) => ({ ...createState(...args) })))
);
