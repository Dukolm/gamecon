import { ProgramDataSlice } from "./example";
import { createProgramDataSlice } from "./example";
import { createMyStore, MyStateCreator } from "../utils";

type ProgramState = ProgramDataSlice;

export type ProgramStateCreator<T> = MyStateCreator<ProgramState, T>;

const createState: ProgramStateCreator<ProgramState> = (...args) => ({
  ...createProgramDataSlice(...args),
});

export const useProgramStore = createMyStore(createState);
