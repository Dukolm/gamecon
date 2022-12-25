import { ProgramDataSlice } from "./programData";
import { createProgramDataSlice } from "./programData";
import { createMyStore, MyStateCreator } from "../common";
import { createProgramUrlSlice, ProgramUrlSlice } from "./url";

type ProgramState = ProgramDataSlice & ProgramUrlSlice;

export type ProgramStateCreator<T> = MyStateCreator<ProgramState, T>;

const createState: ProgramStateCreator<ProgramState> = (...args) => ({
  ...createProgramDataSlice(...args),
  ...createProgramUrlSlice(...args),
});

export const useProgramStore = createMyStore(createState);
