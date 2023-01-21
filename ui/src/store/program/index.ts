import { ProgramDataSlice } from "./programData";
import { createProgramDataSlice } from "./programData";
import { createMyStore, MyStateCreator } from "../common/MyStore";
import { createProgramUrlSlice, ProgramUrlSlice } from "./url";
import { inicializujProgramStoreSubscribe } from "./subscriptions";
import { createPřihlášenýUživatelSlice, PřihlášenýUživatelSlice } from "./přihlášenSlice";

type ProgramState = ProgramDataSlice & ProgramUrlSlice & PřihlášenýUživatelSlice;

export type ProgramStateCreator<T> = MyStateCreator<ProgramState, T>;

const createState: ProgramStateCreator<ProgramState> = (...args) => ({
  ...createProgramDataSlice(...args),
  ...createProgramUrlSlice(...args),
  ...createPřihlášenýUživatelSlice(...args),
});

export const useProgramStore = createMyStore(createState);

inicializujProgramStoreSubscribe();
